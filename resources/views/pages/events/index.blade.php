<?php

use App\Models\Event;
use App\Models\Level;
use App\Models\Bill;
use App\Models\Student;
use App\Models\BillCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Flux\Flux;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="space-y-2">
                    <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                    <div class="h-4 w-64 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-700 rounded-lg animate-pulse"></div>
            </div>
            
            <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse w-full mb-6"></div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 10) as $i)
                        <div class="flex items-center gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-zinc-100 dark:bg-zinc-800 rounded w-1/4 animate-pulse"></div>
                                <div class="h-3 bg-zinc-50 dark:bg-zinc-900 rounded w-1/6 animate-pulse"></div>
                            </div>
                            <div class="w-16 h-6 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                        </div>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>
                    @endforeach
                </div>
            </div>
        </div>
        HTML;
    }

    public $search = '';
    public $confirmingDeletion = false;
    public $deletingId = null;

    // Form Properties
    public $form = [
        'id' => null,
        'title' => '',
        'description' => '',
        'event_date' => '',
        'start_time' => '',
        'end_time' => '',
        'is_paid' => false,
        'amount' => 0,
        'bill_category_id' => null,
        'participation_type' => 'MANDATORY',
        'level_ids' => [],
    ];

    public function mount()
    {
        $this->form['event_date'] = date('Y-m-d');
        $this->form['bill_category_id'] = BillCategory::where('name', 'like', '%Kegiatan%')->first()?->id;
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $events = Event::query()
            ->with(['levels', 'bills']) // Eager load relationships
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->where('title', 'like', '%' . $this->search . '%'))
            ->orderBy('event_date', 'desc')
            ->paginate(10);

        return [
            'events' => $events,
            'levels' => Level::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get(),
            'categories' => BillCategory::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get(),
        ];
    }

    public $showConfirmation = false;

    public function openCreateModal()
    {
        $this->reset(['form', 'showConfirmation']);
        $this->form['event_date'] = date('Y-m-d');
        // Default category for activities if available, or create one
        $category = BillCategory::firstOrCreate(
            ['name' => 'Kegiatan Sekolah'],
            ['school_id' => auth()->user()->school_id ?? null, 'description' => 'Kategori otomatis untuk kegiatan sekolah']
        );
        $this->form['bill_category_id'] = $category->id;
        $this->modal('event-modal')->show();
    }

    public function edit($id)
    {
        $event = Event::with('levels')->findOrFail($id);
        $this->reset('showConfirmation');

        $this->form = [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'event_date' => $event->event_date->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'is_paid' => $event->is_paid,
            'amount' => $event->amount ?? 0,
            'bill_category_id' => $event->bills()->first()?->bill_category_id,
            'participation_type' => $event->participation_type,
            'level_ids' => $event->levels->pluck('id')->toArray(),
        ];

        if (!$this->form['bill_category_id']) {
            $this->form['bill_category_id'] = BillCategory::where('name', 'like', '%Kegiatan%')->first()?->id;
        }

        $this->modal('event-modal')->show();
    }

    public function validateAndConfirm()
    {
        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'required|string',
            'form.event_date' => 'required|date',
            'form.start_time' => 'required',
            'form.end_time' => 'nullable|after:form.start_time',
            'form.is_paid' => 'boolean',
            'form.amount' => 'required_if:form.is_paid,true|numeric|min:0',
            'form.bill_category_id' => 'required_if:form.is_paid,true|nullable|exists:bill_categories,id',
            'form.participation_type' => 'required|in:MANDATORY,OPTIONAL',
            'form.level_ids' => 'required|array|min:1',
            'form.level_ids.*' => 'exists:levels,id',
        ]);

        $this->showConfirmation = true;
    }

    public function backToForm()
    {
        $this->showConfirmation = false;
    }

    public function save()
    {
        // Validation is done in validateAndConfirm, but good to keep basic check just in case
        if (!$this->showConfirmation) {
            $this->validateAndConfirm();
            return;
        }

        DB::transaction(function () {
            $user = auth()->user();
            $schoolId = $user->hasRole('admin') ? $user->school_id : null;

            $data = [
                'school_id' => $schoolId,
                'title' => $this->form['title'],
                'description' => $this->form['description'],
                'event_date' => $this->form['event_date'],
                'start_time' => $this->form['start_time'],
                'end_time' => $this->form['end_time'],
                'is_paid' => $this->form['is_paid'],
                'amount' => $this->form['is_paid'] ? $this->form['amount'] : null,
                'participation_type' => $this->form['participation_type'],
            ];

            if ($this->form['id']) {
                $event = Event::findOrFail($this->form['id']);
                $event->update($data);
                $isNew = false;
            } else {
                $event = Event::create($data);
                $isNew = true;
            }

            // Sync Levels
            $event->levels()->sync($this->form['level_ids']);

            // Create Bills Logic
            if ($this->form['is_paid']) {
                $targetStudents = Student::whereIn('level_id', $this->form['level_ids'])
                    ->where('status', 'ACTIVE')
                    ->where('school_id', $schoolId)
                    ->get();

                $createdCount = 0;

                foreach ($targetStudents as $student) {
                    $exists = Bill::where('event_id', $event->id)
                        ->where('student_id', $student->id)
                        ->exists();

                    if (!$exists) {
                        Bill::create([
                            'school_id' => $schoolId,
                            'invoice_code' => 'INV-EVT-' . time() . '-' . rand(1000, 9999),
                            'student_id' => $student->id,
                            'bill_category_id' => $this->form['bill_category_id'],
                            'event_id' => $event->id,
                            'title' => $event->title,
                            'amount' => $data['amount'],
                            'payment_fee' => 0,
                            'admin_fee' => 0,
                            'status' => 'UNPAID',
                            'due_date' => $data['event_date'],
                            'paid_at' => null,
                            'payment_method' => null,
                        ]);
                        $createdCount++;
                    }
                }

                if ($createdCount > 0) {
                    Flux::toast(variant: 'success', heading: 'Tagihan Dibuat', text: "$createdCount tagihan berhasil dibuat untuk kegiatan ini.");
                }
            }

            Flux::toast(variant: 'success', heading: 'Berhasil', text: $isNew ? 'Kegiatan berhasil ditambahkan.' : 'Kegiatan berhasil diperbarui.');
            $this->modal('event-modal')->close();
            $this->showConfirmation = false;
        });
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->modal('delete-confirm-modal')->show();
    }

    public function delete()
    {
        $event = Event::findOrFail($this->deletingId);

        // Check if event has bills that are PAID?
        // If event has paid bills, maybe prevent delete or archive?
        // For now, cascade delete is set in migration, so it will delete bills too.
        // Let's just delete for now as per schema cascade.
        // But maybe warn user if bills exist.

        $billsCount = $event->bills()->count();
        $paidBillsCount = $event->bills()->where('status', 'PAID')->count();

        if ($paidBillsCount > 0) {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: "Tidak dapat menghapus kegiatan yang memiliki $paidBillsCount tagihan lunas.");
            $this->modal('delete-confirm-modal')->close();
            return;
        }

        $event->delete();

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kegiatan berhasil dihapus.');
        $this->modal('delete-confirm-modal')->close();
    }

    public function getSelectedLevelsLabel()
    {
        if (empty($this->form['level_ids']))
            return 'Pilih Kelas';
        $count = count($this->form['level_ids']);
        return "$count Kelas Terpilih";
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Agenda Kegiatan') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola kegiatan dan acara sekolah') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Tambah Kegiatan') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari kegiatan..." />

        <flux:table :paginate="$events">
            <flux:table.columns>
                <flux:table.column>Judul</flux:table.column>
                <flux:table.column>Tanggal & Waktu</flux:table.column>
                <flux:table.column>Peserta</flux:table.column>
                <flux:table.column>Biaya</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($events as $event)
                    <flux:table.row :key="$event->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $event->title }}</div>
                            <div class="text-sm text-zinc-500 truncate max-w-xs">{{ Str::limit($event->description, 50) }}
                            </div>
                            @if($event->participation_type === 'MANDATORY')
                                <flux:badge size="sm" color="red" class="mt-1">Wajib</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" class="mt-1">Opsional</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="w-4 h-4 text-zinc-400" />
                                <span>{{ $event->event_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1 text-sm text-zinc-500">
                                <flux:icon.clock class="w-4 h-4 text-zinc-400" />
                                <span>{{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }} -
                                    {{ $event->end_time ? \Carbon\Carbon::parse($event->end_time)->format('H:i') : 'Selesai' }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach($event->levels->take(3) as $level)
                                    <flux:badge size="sm">{{ $level->name }}</flux:badge>
                                @endforeach
                                @if($event->levels->count() > 3)
                                    <flux:badge size="sm">+{{ $event->levels->count() - 3 }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($event->is_paid)
                                <div class="font-medium text-red-600 dark:text-red-400">
                                    Rp {{ number_format($event->amount, 0, ',', '.') }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    {{ $event->bills_count ?? 0 }} Tagihan
                                </div>
                            @else
                                <flux:badge color="green">Gratis</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                                <flux:menu>
                                    <flux:menu.item wire:click="edit('{{ $event->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                    <flux:menu.item href="{{ route('events.attendance.entry', $event->id) }}"
                                        icon="clipboard-document-check" wire:navigate>Presensi</flux:menu.item>
                                    <flux:menu.item wire:click="confirmDelete('{{ $event->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.calendar class="w-8 h-8 text-zinc-300" />
                                <div class="text-zinc-500">Belum ada kegiatan</div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

    </div>

    <!-- Create/Edit Modal with Flyout -->
    <flux:modal name="event-modal" flyout class="space-y-6">
        @if(!$showConfirmation)
            <!-- Step 1: Input Form -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $form['id'] ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru' }}</flux:heading>
                    <flux:subheading>Isi detail kegiatan di bawah ini.</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-6">
                    <flux:input wire:model="form.title" label="Nama Kegiatan"
                        placeholder="Contoh: Field Trip, Ujian Tengah Semester" />

                    <flux:textarea wire:model="form.description" label="Deskripsi"
                        placeholder="Deskripsi lengkap kegiatan..." />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:input wire:model="form.event_date" type="date" label="Tanggal" />
                        <flux:input wire:model="form.start_time" type="time" label="Waktu Mulai" />
                        <flux:input wire:model="form.end_time" type="time" label="Waktu Selesai" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select wire:model="form.participation_type" label="Sifat Kegiatan" variant="listbox">
                            <flux:select.option value="MANDATORY">Wajib</flux:select.option>
                            <flux:select.option value="OPTIONAL">Opsional</flux:select.option>
                        </flux:select>

                        <!-- Level Multi-Select -->
                        <flux:select wire:model="form.level_ids" variant="listbox" multiple label="Peserta (Kelas)"
                            placeholder="Pilih kelas...">
                            @foreach($levels as $level)
                                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:separator variant="subtle" />

                    <!-- Payment Settings -->
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:label>Kegiatan Berbayar?</flux:label>
                                <div class="text-xs text-zinc-500">Jika aktif, tagihan akan otomatis dibuat untuk peserta.
                                </div>
                            </div>
                            <flux:switch wire:model.live="form.is_paid" />
                        </div>

                        @if($form['is_paid'])
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <flux:input wire:model="form.amount" type="number" label="Biaya (Rp)" placeholder="0" />

                                <flux:select wire:model="form.bill_category_id" label="Kategori Tagihan" variant="listbox"
                                    placeholder="Pilih kategori...">
                                    @foreach($categories as $category)
                                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="validateAndConfirm" variant="primary">Lanjut ke Konfirmasi</flux:button>
                </div>
            </div>
        @else
            <!-- Step 2: Confirmation -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Konfirmasi Kegiatan</flux:heading>
                    <flux:subheading>Pastikan data berikut sudah benar sebelum disimpan.</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-6">
                    <div
                        class="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <flux:icon.calendar class="w-24 h-24" />
                        </div>

                        <div class="relative z-10 space-y-4">
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Nama Kegiatan</div>
                                <div class="text-xl font-bold text-zinc-900 dark:text-white">{{ $form['title'] }}</div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Tanggal &
                                        Waktu</div>
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($form['event_date'])->format('d M Y') }} <br>
                                        {{ $form['start_time'] }} - {{ $form['end_time'] ?? 'Selesai' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Peserta
                                    </div>
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ count($form['level_ids']) }} Kelas
                                    </div>
                                </div>
                            </div>

                            <flux:separator variant="subtle" />

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Sifat</div>
                                    <flux:badge size="sm"
                                        color="{{ $form['participation_type'] === 'MANDATORY' ? 'red' : 'zinc' }}">
                                        {{ $form['participation_type'] === 'MANDATORY' ? 'Wajib' : 'Opsional' }}
                                    </flux:badge>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Biaya</div>
                                    @if($form['is_paid'])
                                        <span class="font-bold text-red-600">Rp
                                            {{ number_format($form['amount'], 0, ',', '.') }}</span>
                                    @else
                                        <flux:badge color="green">Gratis</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($form['is_paid'])
                        <div
                            class="flex gap-4 p-4 bg-orange-50 dark:bg-orange-900/20 text-orange-800 dark:text-orange-200 rounded-lg border border-orange-200 dark:border-orange-800/30">
                            <flux:icon.exclamation-triangle class="w-5 h-5 flex-shrink-0 mt-0.5" />
                            <div class="text-sm">
                                <p class="font-medium mb-1">Perhatian</p>
                                <p class="opacity-90">
                                    Anda akan membuat kegiatan berbayar.
                                    Tagihan akan <strong>otomatis dibuat</strong> untuk semua siswa di
                                    {{ count($form['level_ids']) }} kelas yang dipilih.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex gap-2">
                    <flux:button variant="ghost" wire:click="backToForm">Kembali & Edit</flux:button>
                    <flux:spacer />
                    <flux:button wire:click="save" variant="primary">Simpan & Proses</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Delete Confirmation -->
    <flux:modal name="delete-confirm-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-red-500">Hapus Kegiatan?</flux:heading>
                <flux:subheading>
                    Tindakan ini tidak dapat dibatalkan. Tagihan yang belum lunas terkait kegiatan ini juga akan
                    dihapus.
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>