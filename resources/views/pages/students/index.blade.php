<?php

use App\Models\Department;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Services\ImageCompressionService;
use Illuminate\Support\Str;
use Flux\Flux;

new #[Lazy] class extends Component {
    use WithPagination, WithFileUploads;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="space-y-2">
                    <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                    <div class="h-4 w-64 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                </div>
                <div class="flex gap-2">
                    <div class="h-10 w-24 bg-zinc-100 dark:bg-zinc-800 rounded-lg animate-pulse"></div>
                    <div class="h-10 w-32 bg-zinc-100 dark:bg-zinc-800 rounded-lg animate-pulse"></div>
                    <div class="h-10 w-36 bg-zinc-200 dark:bg-zinc-700 rounded-lg animate-pulse"></div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 5) as $i)
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 animate-pulse"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-zinc-100 dark:bg-zinc-800 rounded w-1/4 animate-pulse"></div>
                                <div class="h-3 bg-zinc-50 dark:bg-zinc-900 rounded w-1/6 animate-pulse"></div>
                            </div>
                            <div class="w-24 h-6 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                        </div>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>
                    @endforeach
                </div>
            </div>
        </div>
        HTML;
    }

    #[Url]
    public $tab = 'all';

    #[Url]
    public $search = '';

    #[Url]
    public $levelFilter = '';

    #[Url]
    public $departmentFilter = '';

    #[Url]
    public $parentFilter = '';

    // Form fields
    public $studentId = null;
    public $nis = '';
    public $full_name = '';
    public $level_id = '';
    public $parent_user_id = '';
    public $gender = 'L';
    public $status = 'ACTIVE';

    // Photo
    public $tempPhoto;
    public $current_photo_url;

    public function updatedTempPhoto()
    {
        $this->validate([
            'tempPhoto' => 'image|max:10240',
        ]);
    }

    // Delete
    public $studentIdToDelete = null;

    // Bulk Generate
    public $bulkCount = 0;

    public function openBulkGenerateModal()
    {
        $this->bulkCount = Student::doesntHave('card')
            ->where('status', 'ACTIVE')
            ->count();

        $this->modal('bulk-generate-modal')->show();
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        // Base Query
        $query = Student::query()
            ->with(['school', 'level:id,name,department_id', 'level.department:id,name', 'parent:id,name', 'card:id,student_id,card_number,status'])
            ->orderBy('full_name');

        // Apply Scope based on Tab
        if ($this->tab === 'incomplete') {
            $query->incomplete();
        }

        // Apply filters
        $query->when($this->search, fn($q) => $q->where(function ($sub) {
            $sub->where('full_name', 'like', '%' . $this->search . '%')
                ->orWhere('nis', 'like', '%' . $this->search . '%');
        }))
            ->when($this->levelFilter, fn($q) => $q->where('level_id', $this->levelFilter))
            ->when($this->departmentFilter, fn($q) => $q->whereHas('level', fn($l) => $l->where('department_id', $this->departmentFilter)))
            ->when($this->parentFilter, fn($q) => $q->where('parent_user_id', $this->parentFilter));

        // Data for filters
        $departments = Department::query()
            ->select('id', 'name')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->get();

        $levels = Level::query()
            ->select('id', 'name', 'department_id')
            ->with('department:id,name')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->departmentFilter, fn($q) => $q->where('department_id', $this->departmentFilter))
            ->get();

        $parents = User::role('wali_santri')
            ->select('users.id', 'users.name')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        return [
            'students' => $query->paginate(15),
            'levels' => $levels,
            'departments' => $departments,
            'parents' => $parents,
            'currentStudentInstance' => $this->studentId ? Student::find($this->studentId) : null,
        ];
    }

    // Reset pagination when tab changes
    public function updatedTab()
    {
        $this->resetPage();
    }

    public function save()
    {
        $this->validate([
            'nis' => [
                'required',
                'string',
                'max:20',
                Rule::unique('students')->where(fn($query) => $query->where('school_id', auth()->user()->school_id))->ignore($this->studentId)
            ],
            'full_name' => 'required|string|max:255',
            'level_id' => 'required|exists:levels,id',
            'parent_user_id' => 'nullable|exists:users,id',
            'gender' => 'required|in:L,P',
            'status' => 'required|in:ACTIVE,INACTIVE,GRADUATED,DROPPED_OUT',
        ]);

        DB::transaction(function () {
            $data = [
                'nis' => $this->nis,
                'full_name' => $this->full_name,
                'level_id' => $this->level_id,
                'parent_user_id' => $this->parent_user_id ?: null,
                'gender' => $this->gender,
                'status' => $this->status,
            ];

            if (!$this->studentId) {
                if (auth()->user()->hasRole('admin')) {
                    $data['school_id'] = auth()->user()->school_id;
                } else {
                    $data['school_id'] = auth()->user()->school_id;
                }
            }

            if ($this->tempPhoto) {
                $service = new ImageCompressionService();
                $data['photo_url'] = $service->storeAsWebP($this->tempPhoto, 'students/photos', Str::uuid());
            }

            if ($this->studentId) {
                $student = Student::find($this->studentId);
                $student->update($data);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data santri berhasil diperbarui.');
            } else {
                Student::create($data);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data santri berhasil ditambahkan.');
            }
        });

        $this->reset(['studentId', 'nis', 'full_name', 'level_id', 'parent_user_id', 'gender', 'status', 'tempPhoto', 'current_photo_url']);
        $this->resetValidation();
        $this->modal('student-modal')->close();
    }

    public function edit($id)
    {
        $student = Student::find($id);
        $this->studentId = $student->id;
        $this->nis = $student->nis;
        $this->full_name = $student->full_name;
        $this->level_id = $student->level_id;
        $this->parent_user_id = $student->parent_user_id;
        $this->gender = $student->gender;
        $this->status = $student->status;
        $this->current_photo_url = $student->photo_url;
        $this->reset('tempPhoto');

        $this->modal('student-modal')->show();
    }

    public function create()
    {
        $this->reset(['studentId', 'nis', 'full_name', 'level_id', 'parent_user_id', 'gender', 'status', 'tempPhoto', 'current_photo_url']);
        $this->resetValidation();
        $this->modal('student-modal')->show();
    }

    public function confirmDelete($id)
    {
        $this->studentIdToDelete = $id;
        $this->modal('delete-student-modal')->show();
    }

    public function destroy()
    {
        $student = Student::find($this->studentIdToDelete);
        if ($student) {
            $student->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data santri berhasil dihapus.');
        }
        $this->reset('studentIdToDelete');
        $this->modal('delete-student-modal')->close();
    }

    // Standalone photo upload methods removed

    public function generateCard($id)
    {
        $student = Student::find($id);
        if (!$student)
            return;

        if ($student->card) {
            Flux::toast(variant: 'warning', heading: 'Info', text: 'Santri ini sudah memiliki kartu.');
            return;
        }

        do {
            $cardNumber = '';
            for ($i = 0; $i < 16; $i++) {
                $cardNumber .= mt_rand(0, 9);
            }
        } while (\App\Models\StudentCard::where('card_number', $cardNumber)->exists());

        \App\Models\StudentCard::create([
            'student_id' => $student->id,
            'issued_by' => auth()->id(),
            'card_number' => $cardNumber,
            'issued_at' => now(),
            'status' => 'generated',
        ]);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kartu santri berhasil digenerate.');
    }

    public function bulkGenerate()
    {
        $limit = 100;
        $students = Student::doesntHave('card')
            ->where('status', 'ACTIVE')
            ->limit($limit)
            ->get();

        if ($students->isEmpty()) {
            Flux::toast(variant: 'info', heading: 'Info', text: 'Semua santri aktif sudah memiliki kartu.');
            return;
        }

        $count = 0;
        foreach ($students as $student) {
            do {
                $cardNumber = '';
                for ($i = 0; $i < 16; $i++) {
                    $cardNumber .= mt_rand(0, 9);
                }
            } while (\App\Models\StudentCard::where('card_number', $cardNumber)->exists());

            \App\Models\StudentCard::create([
                'student_id' => $student->id,
                'issued_by' => auth()->id(),
                'card_number' => $cardNumber,
                'issued_at' => now(),
                'status' => 'generated',
            ]);
            $count++;
        }

        Flux::toast(
            variant: 'success',
            heading: 'Bulk Generate Berhasil',
            text: "Berhasil generate kartu untuk {$count} santri."
        );
        $this->modal('bulk-generate-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Santri') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola data santri sekolah') }}</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button variant="filled" icon="arrow-up-tray" href="{{ route('students.import') }}" wire:navigate>
                    {{ __('Import') }}
                </flux:button>
                <flux:button variant="filled" icon="credit-card" wire:click="openBulkGenerateModal">
                    {{ __('Generate Kartu') }}
                </flux:button>

                <flux:modal.trigger name="student-modal">
                    <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Santri') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari nama / NIS..." />

        <!-- Show extra filters only for 'all' tab or if needed -->
        @if($tab === 'all')
            <flux:select wire:model.live="departmentFilter" variant="listbox" placeholder="Filter Jenjang" clearable>
                <flux:select.option value="">Semua Jenjang</flux:select.option>
                @foreach ($departments as $dept)
                    <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="levelFilter" variant="listbox" placeholder="Filter Kelas" clearable>
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach ($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if($tab === 'all')
            <flux:select wire:model.live="parentFilter" variant="combobox" placeholder="Filter Orang Tua" clearable>
                <flux:select.option value="">Semua Orang Tua</flux:select.option>
                @foreach ($parents as $parent)
                    <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:tab.group wire:model.live="tab">
        <flux:tabs>
            <flux:tab name="all" icon="users">Semua Data</flux:tab>
            <flux:tab name="incomplete" icon="exclamation-circle">Data Belum Lengkap</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="all">
            <flux:table :paginate="$students">
                <flux:table.columns>
                    <flux:table.column>Nama Santri</flux:table.column>
                    <flux:table.column>Akademik</flux:table.column>
                    <flux:table.column>Wali Santri</flux:table.column>
                    <flux:table.column>Gender</flux:table.column>
                    <flux:table.column>Dompet & Transaksi</flux:table.column>
                    <flux:table.column>Kartu</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($students as $student)
                        <flux:table.row :key="$student->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$student->full_name" :src="$student->photo_url" size="sm" />
                                    <div>
                                        <div class="font-medium">{{ $student->full_name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $student->nis }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col gap-1">
                                    @if($student->level)
                                        <flux:badge size="sm" color="zinc">{{ $student->level->department->name ?? '-' }}
                                        </flux:badge>
                                        <flux:badge size="sm" color="zinc" variant="outline">{{ $student->level->name }}
                                        </flux:badge>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($student->parent)
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$student->parent->name" :initials="$student->parent->initials()"
                                            size="xs" />
                                        <span class="text-sm">{{ $student->parent->name }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400 italic">Belum ada wali</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($student->gender == 'L')
                                    <flux:badge size="sm" color="blue">Laki-laki</flux:badge>
                                @else
                                    <flux:badge size="sm" color="pink">Perempuan</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold">Rp
                                        {{ number_format($student->wallet_balance, 0, ',', '.') }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                                        <div class="flex items-center gap-1" title="Status PIN Dompet">
                                            <flux:icon name="{{ $student->hasPin() ? 'lock-closed' : 'lock-open' }}"
                                                size="xs"
                                                class="{{ $student->hasPin() ? 'text-emerald-500' : 'text-amber-500' }}" />
                                            <span>{{ $student->hasPin() ? 'PIN Set' : 'No PIN' }}</span>
                                        </div>
                                        <span class="text-zinc-300">|</span>
                                        <div class="flex items-center gap-1" title="Batas Tarik Tunai Harian">
                                            <flux:icon name="banknotes" size="xs" />
                                            <span>{{ number_format($student->daily_cash_limit, 0, ',', '.') }}</span>
                                        </div>
                                        <span class="text-zinc-300">|</span>
                                        <div class="flex items-center gap-1" title="Izin Transaksi">
                                            <flux:icon
                                                name="{{ $student->allow_transaction ? 'check-circle' : 'x-circle' }}"
                                                size="xs"
                                                class="{{ $student->allow_transaction ? 'text-emerald-500' : 'text-red-500' }}" />
                                            <span>{{ $student->allow_transaction ? 'Allowed' : 'Blocked' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($student->card)
                                    @php
                                        $statusClasses = match ($student->card->status) {
                                            'generated' => '!text-zinc-600 !bg-zinc-50 hover:!bg-zinc-100 dark:!text-zinc-400 dark:!bg-zinc-900/20 dark:hover:!bg-zinc-900/40',
                                            'request_print' => '!text-orange-600 !bg-orange-50 hover:!bg-orange-100 dark:!text-orange-400 dark:!bg-orange-900/20 dark:hover:!bg-orange-900/40',
                                            'printed' => '!text-green-600 !bg-green-50 hover:!bg-green-100 dark:!text-green-400 dark:!bg-green-900/20 dark:hover:!bg-green-900/40',
                                            default => '!text-zinc-600 !bg-zinc-50 hover:!bg-zinc-100 dark:!text-zinc-400 dark:!bg-zinc-900/20 dark:hover:!bg-zinc-900/40'
                                        };
                                    @endphp
                                    <flux:button size="sm" variant="subtle" class="{{ $statusClasses }}" icon="credit-card"
                                        href="{{ route('student-cards.index', ['search' => $student->card->card_number]) }}"
                                        wire:navigate>
                                        {{ $student->card->card_number }}
                                    </flux:button>
                                @else
                                    <flux:badge size="sm" color="zinc" variant="outline" icon="x-mark">Belum Ada</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $colors = [
                                        'ACTIVE' => 'emerald',
                                        'INACTIVE' => 'zinc',
                                        'GRADUATED' => 'blue',
                                        'DROPPED_OUT' => 'red',
                                    ];
                                @endphp
                                <flux:badge size="sm" :color="$colors[$student->status] ?? 'zinc'">
                                    {{ str_replace('_', ' ', $student->status) }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:modal.trigger name="student-modal">
                                            <flux:menu.item wire:click="edit('{{ $student->id }}')" icon="pencil-square">
                                                Edit
                                            </flux:menu.item>
                                        </flux:modal.trigger>
                                        <flux:modal.trigger name="delete-student-modal">
                                            <flux:menu.item wire:click="confirmDelete('{{ $student->id }}')" icon="trash"
                                                variant="danger">Hapus</flux:menu.item>
                                        </flux:modal.trigger>
                                        <flux:menu.separator />
                                        @if(!$student->card)
                                            <flux:menu.item wire:click="generateCard('{{ $student->id }}')" icon="credit-card">
                                                Generate Kartu
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item disabled icon="check-circle" class="opacity-50 cursor-not-allowed">
                                                Kartu Generated
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-12">
                                <div class="flex flex-col items-center gap-3">
                                    <flux:icon.academic-cap class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                                    <div>
                                        <flux:heading size="lg" class="text-zinc-500">Tidak ada santri</flux:heading>
                                        <flux:subheading>Belum ada data santri. Klik "Tambah Santri" untuk menambahkan.
                                        </flux:subheading>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:tab.panel>

        <flux:tab.panel name="incomplete">
            <flux:table :paginate="$students">
                <flux:table.columns>
                    <flux:table.column>Nama Santri</flux:table.column>
                    <flux:table.column>Data Belum Lengkap</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($students as $student)
                        <flux:table.row :key="$student->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$student->full_name" :src="$student->photo_url" size="sm" />
                                    <div>
                                        <div class="font-medium">{{ $student->full_name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $student->nis }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($student->missing_fields as $field)
                                        <flux:badge color="red" size="sm" icon="exclamation-circle">{{ $field }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:modal.trigger name="student-modal">
                                    <flux:button variant="primary" size="sm" icon="pencil-square"
                                        wire:click="edit('{{ $student->id }}')">Lengkapi Data</flux:button>
                                </flux:modal.trigger>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center py-12">
                                <div class="flex flex-col items-center gap-3">
                                    <flux:icon.check-circle class="w-12 h-12 text-emerald-500" />
                                    <div>
                                        <flux:heading size="lg" class="text-zinc-500">Semua Data Lengkap</flux:heading>
                                        <flux:subheading>Tidak ada santri dengan data yang belum lengkap.</flux:subheading>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:tab.panel>
    </flux:tab.group>

    <!-- Modal Create/Edit -->
    <flux:modal name="student-modal" flyout class="space-y-6 md:w-[30rem]">
        <form wire:submit="save" class="space-y-6" x-data="{ 
        cropping: false, 
        cropper: null, 
        tempImageUrl: null,
        
        initCropper(file) {
            if (!file) return;
            this.cropping = true;
            
            let reader = new FileReader();
            reader.onload = (e) => {
                this.tempImageUrl = e.target.result;
                
                if(this.cropper) {
                    this.cropper.destroy();
                }
                
                setTimeout(() => {
                    let image = this.$refs.photoCropper;
                    if (image) {
                        this.cropper = new Cropper(image, {
                            aspectRatio: 1, 
                            viewMode: 1,
                            autoCropArea: 1,
                            background: false,
                        });
                    }
                }, 100);
            };
            reader.readAsDataURL(file);
        },
        
        crop() {
            if(!this.cropper) return;
            
            this.cropper.getCroppedCanvas().toBlob((blob) => {
                if (!blob) {
                    alert('Crop failed'); 
                    return;
                }
                
                let file = new File([blob], 'student_photo_' + Date.now() + '.png', { type: 'image/png' });
                
                console.log('Start Upload:', file);

                // Upload using Livewire upload API
                @this.upload('tempPhoto', file, (uploadedFilename) => {
                    console.log('Upload Success:', uploadedFilename);
                    this.resetState();
                }, (error) => {
                    console.error('Upload Error:', error);
                    let msg = 'Upload gagal';
                    if (error) {
                        msg += ': ' + (error.message || JSON.stringify(error));
                    }
                    alert(msg);
                    this.resetState();
                });
                
            }, 'image/png', 0.8);
        },
        
        resetState() {
            this.cropping = false;
            if(this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.tempImageUrl = null;
            if(this.$refs.photoInput) this.$refs.photoInput.value = '';
        }
    }">
            <div>
                <flux:heading size="lg">{{ $studentId ? 'Edit Santri' : 'Tambah Santri' }}</flux:heading>
                <flux:subheading>{{ $studentId ? 'Perbarui data santri.' : 'Tambahkan data santri baru.' }}
                </flux:subheading>
            </div>

            <!-- Photo Upload Section -->
            <div class="space-y-4">
                <flux:label>Foto Profil</flux:label>

                <!-- Cropping Area -->
                <div x-show="cropping" style="display: none;" class="space-y-4">
                    <div
                        class="w-full h-64 bg-zinc-900 rounded-lg overflow-hidden relative flex items-center justify-center">
                        <img x-ref="photoCropper" :src="tempImageUrl" class="block max-w-full max-h-full">
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" @click="resetState()" variant="ghost" class="w-full">Batal
                        </flux:button>
                        <flux:button type="button" @click="crop()" variant="primary" class="w-full">Terapkan
                        </flux:button>
                    </div>
                </div>

                <!-- Preview & Input -->
                <div x-show="!cropping" class="flex items-center gap-4">
                    <div class="shrink-0 relative">
                        @if ($tempPhoto)
                            <img src="{{ $tempPhoto->temporaryUrl() }}"
                                class="w-20 h-20 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700">
                        @elseif ($current_photo_url)
                            <img src="{{ $current_photo_url }}"
                                class="w-20 h-20 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700">
                        @else
                            <div
                                class="w-20 h-20 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                                <flux:icon.user class="w-10 h-10" />
                            </div>
                        @endif

                        <label
                            class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded-full p-1 shadow-sm border border-zinc-200 dark:border-zinc-700 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700 transition">
                            <flux:icon.camera class="w-4 h-4 text-zinc-500" />
                            <input type="file" x-ref="photoInput" class="hidden" accept="image/*"
                                @change="initCropper($event.target.files[0])">
                        </label>
                    </div>
                    <div>
                        <flux:text size="sm">Upload foto profil (opsional)</flux:text>
                        <flux:text size="xs" variant="subtle">Akan di-crop rasio 1:1 otomatis</flux:text>
                    </div>
                </div>
                <flux:error name="tempPhoto" />
            </div>

            <flux:field>
                <flux:label badge="Required">NIS</flux:label>
                <flux:input wire:model="nis" placeholder="Nomor Induk Santri" />
                <flux:error name="nis" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Nama Lengkap</flux:label>
                <flux:input wire:model="full_name" placeholder="Nama Lengkap Santri" />
                <flux:error name="full_name" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Required">Gender</flux:label>
                    <flux:select wire:model="gender" variant="listbox" placeholder="Pilih">
                        <flux:select.option value="L">Laki-laki</flux:select.option>
                        <flux:select.option value="P">Perempuan</flux:select.option>
                    </flux:select>
                    <flux:error name="gender" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Required">Status</flux:label>
                    <flux:select wire:model="status" variant="listbox" placeholder="Pilih">
                        <flux:select.option value="ACTIVE">Active</flux:select.option>
                        <flux:select.option value="INACTIVE">Inactive</flux:select.option>
                        <flux:select.option value="GRADUATED">Graduated</flux:select.option>
                        <flux:select.option value="DROPPED_OUT">Dropped Out</flux:select.option>
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Required">Kelas</flux:label>
                <flux:select wire:model="level_id" variant="combobox" placeholder="Pilih Kelas">
                    @foreach ($levels as $level)
                        <flux:select.option value="{{ $level->id }}">
                            {{ $level->name }} ({{ $level->department->name ?? '-' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="level_id" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Wali Santri</flux:label>
                <flux:select wire:model="parent_user_id" variant="combobox" placeholder="Pilih Wali Santri">
                    <flux:select.option value="">Tidak Ada / Belum Ditentukan</flux:select.option>
                    @foreach ($parents as $parent)
                        <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="parent_user_id" />
            </flux:field>

            @if($studentId && $currentStudentInstance)
                <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2">Info Dompet (Read Only)</flux:heading>
                    <div class="text-xs text-zinc-500 space-y-1">
                        <div class="flex justify-between">
                            <span>PIN Status:</span>
                            <span
                                class="font-medium {{ $currentStudentInstance->hasPin() ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $currentStudentInstance->hasPin() ? 'Terpasang' : 'Belum Terpasang' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Limit Harian:</span>
                            <span class="font-medium">Rp
                                {{ number_format($currentStudentInstance->daily_cash_limit, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Izin Transaksi:</span>
                            <span
                                class="font-medium">{{ $currentStudentInstance->allow_transaction ? 'Diizinkan' : 'Diblokir' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Bulk Generate Modal -->
    <flux:modal name="bulk-generate-modal" class="min-w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Generate Kartu Massal?</flux:heading>
                <div class="mt-3 space-y-3 text-sm text-zinc-500">
                    <p>
                        Akan dilakukan generate kartu untuk <strong
                            class="text-zinc-900 dark:text-zinc-100">{{ min(100, $bulkCount) }}</strong> santri
                        @if($bulkCount > 100)
                            dari total <strong class="text-zinc-900 dark:text-zinc-100">{{ $bulkCount }}</strong> santri
                        @endif
                        yang belum memiliki kartu.
                    </p>

                    <div
                        class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 rounded-lg border border-amber-200 dark:border-amber-800/30">
                        <p class="font-medium mb-1">Batasan Sistem</p>
                        <p class="text-xs opacity-90">
                            Sistem membatasi proses maksimal <strong>100 data</strong> per generate untuk menjaga
                            performa server agar tidak overload.
                        </p>
                        @if($bulkCount > 100)
                            <p class="text-xs opacity-90 mt-2 font-medium">
                                Karena data lebih dari 100, silakan ulangi proses int ini kembali setelah selesai.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="bulkGenerate" variant="primary">Lanjutkan Generate</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Modal -->
    <flux:modal name="delete-student-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Santri?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus data santri ini?<br>
                    Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="destroy" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>

</div>