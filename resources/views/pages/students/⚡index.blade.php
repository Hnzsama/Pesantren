<?php

use App\Models\Department;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new class extends Component {
    use WithPagination;

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

    // Delete
    public $studentIdToDelete = null;

    public function with(): array
    {
        $user = auth()->user();
        $query = Student::query()
            ->with(['school', 'level', 'level.department', 'parent'])
            ->orderBy('full_name');

        // Filter by School
        if ($user->hasRole('admin')) {
            $query->where('school_id', $user->school_id);
        }

        // Apply filters
        $query->when($this->search, fn($q) => $q->where('full_name', 'like', '%' . $this->search . '%')
            ->orWhere('nis', 'like', '%' . $this->search . '%'))
            ->when($this->levelFilter, fn($q) => $q->where('level_id', $this->levelFilter))
            // Department filter is tricky via HasOneThrough in search, but simpler to filter by levels belonging to Dept if needed.
            // For simplicity in this iteration, we might iterate levels or use whereHas if department relation is set up correctly in query.
            // Since Student has 'level', and Level has 'department_id', we can filter:
            ->when($this->departmentFilter, fn($q) => $q->whereHas('level', fn($l) => $l->where('department_id', $this->departmentFilter)))
            ->when($this->parentFilter, fn($q) => $q->where('parent_user_id', $this->parentFilter));

        // Data for filters
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $departments = Department::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->get();

        $levels = Level::query()
            ->with('department')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->departmentFilter, fn($q) => $q->where('department_id', $this->departmentFilter))
            ->get();

        $parents = User::role('wali_santri')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        return [
            'students' => $query->paginate(10),
            'levels' => $levels,
            'departments' => $departments,
            'parents' => $parents,
        ];
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

            // If creating, assign school_id
            if (!$this->studentId) {
                // If super_admin, we might need a school picker, but for now assuming this page is mostly for School Admin context
                // Or if Super Admin, we need to know WHICH school.
                // Requirement said "School Admin Features", so adapting for School Admin primarily.
                // If Super Admin uses this, they fallback to their own school_id or null? 
                // Let's assume auth user has school_id for simplicity or force school_id if field exists.
                if (auth()->user()->hasRole('admin')) {
                    $data['school_id'] = auth()->user()->school_id;
                } else {
                    // Fallback or error for Super Admin without school context in this specific simple view
                    // ideally Super Admin picks school. But for now let's use user's school_id or error.
                    // To follow previous pattern:
                    $data['school_id'] = auth()->user()->school_id; // Just for safety
                }
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

        $this->reset(['studentId', 'nis', 'full_name', 'level_id', 'parent_user_id', 'gender', 'status']);
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

        $this->modal('student-modal')->show();
    }

    public function create()
    {
        $this->reset(['studentId', 'nis', 'full_name', 'level_id', 'parent_user_id', 'gender', 'status']);
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
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Santri') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola data santri sekolah') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="student-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Santri') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari nama / NIS..." />

        <flux:select wire:model.live="departmentFilter" variant="listbox" placeholder="Filter Jenjang" clearable>
            <flux:select.option value="">Semua Jenjang</flux:select.option>
            @foreach ($departments as $dept)
                <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="levelFilter" variant="listbox" placeholder="Filter Kelas" clearable>
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach ($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="parentFilter" variant="combobox" placeholder="Filter Orang Tua" clearable>
            <flux:select.option value="">Semua Orang Tua</flux:select.option>
            @foreach ($parents as $parent)
                <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$students">
        <flux:table.columns>
            <flux:table.column>Nama Santri</flux:table.column>
            <flux:table.column>Akademik</flux:table.column>
            <flux:table.column>Wali Santri</flux:table.column>
            <flux:table.column>Gender</flux:table.column>
            <flux:table.column>Dompet & Transaksi</flux:table.column>
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
                                <flux:badge size="sm" color="zinc">{{ $student->level->department->name ?? '-' }}</flux:badge>
                                <flux:badge size="sm" color="zinc" variant="outline">{{ $student->level->name }}</flux:badge>
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
                            <div class="text-sm font-semibold">Rp {{ number_format($student->wallet_balance, 0, ',', '.') }}
                            </div>
                            <div class="flex items-center gap-2 text-xs text-zinc-500">
                                <div class="flex items-center gap-1" title="Status PIN Dompet">
                                    <flux:icon name="{{ $student->hasPin() ? 'lock-closed' : 'lock-open' }}" size="xs"
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
                                    <flux:icon name="{{ $student->allow_transaction ? 'check-circle' : 'x-circle' }}"
                                        size="xs"
                                        class="{{ $student->allow_transaction ? 'text-emerald-500' : 'text-red-500' }}" />
                                    <span>{{ $student->allow_transaction ? 'Allowed' : 'Blocked' }}</span>
                                </div>
                            </div>
                        </div>
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
                                    <flux:menu.item wire:click="edit('{{ $student->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:modal.trigger name="delete-student-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $student->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
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

    <!-- Modal Create/Edit -->
    <flux:modal name="student-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $studentId ? 'Edit Santri' : 'Tambah Santri' }}</flux:heading>
                <flux:subheading>{{ $studentId ? 'Perbarui data santri.' : 'Tambahkan data santri baru.' }}
                </flux:subheading>
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

            @if($studentId)
                <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2">Info Dompet (Read Only)</flux:heading>
                    <div class="text-xs text-zinc-500 space-y-1">
                        <div class="flex justify-between">
                            <span>PIN Status:</span>
                            <span
                                class="font-medium {{ $this->studentId && Student::find($this->studentId)->hasPin() ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $this->studentId && Student::find($this->studentId)->hasPin() ? 'Terpasang' : 'Belum Terpasang' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Limit Harian:</span>
                            <span class="font-medium">Rp
                                {{ number_format($this->studentId ? Student::find($this->studentId)->daily_cash_limit : 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Izin Transaksi:</span>
                            <span
                                class="font-medium">{{ $this->studentId && Student::find($this->studentId)->allow_transaction ? 'Diizinkan' : 'Diblokir' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
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