<?php

use App\Models\Bill;
use App\Models\BillCategory;
use App\Models\Level;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $studentFilter = '';

    #[Url]
    public $categoryFilter = '';

    #[Url]
    public $statusFilter = '';

    public $selectedBill = null;

    // Form fields
    public $selection_mode = 'individual'; // individual, level, all
    public $student_ids = [];
    public $level_id = '';
    public $bill_category_id = '';
    public $title = '';
    public $amount = '';
    public $due_date = '';
    public $showConfirmation = false;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Bill::query()
            ->with(['student', 'category', 'school'])
            ->whereNull('event_id') // Exclude event bills
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->studentFilter, fn($q) => $q->where('student_id', $this->studentFilter))
            ->when($this->categoryFilter, fn($q) => $q->where('bill_category_id', $this->categoryFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn($q) => $q->where(function ($query) {
                $query->where('invoice_code', 'like', '%' . $this->search . '%')
                    ->orWhere('title', 'like', '%' . $this->search . '%');
            }))
            ->orderBy('created_at', 'desc');

        $students = Student::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->get();

        $levels = Level::query()
            ->with('department')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        $categories = BillCategory::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        return [
            'bills' => $query->paginate(15),
            'students' => $students,
            'levels' => $levels,
            'categories' => $categories,
        ];
    }

    public function viewDetail($id)
    {
        $this->selectedBill = Bill::with(['student', 'category'])->find($id);
        $this->modal('bill-detail-modal')->show();
    }

    public function closeDetail()
    {
        $this->selectedBill = null;
        $this->modal('bill-detail-modal')->close();
    }

    public function openCreateModal()
    {
        $this->reset(['selection_mode', 'student_ids', 'level_id', 'bill_category_id', 'title', 'amount', 'due_date', 'showConfirmation']);
        $this->resetValidation();
        $this->modal('bill-create-modal')->show();
    }
    public function validateAndConfirm()
    {
        // dd([
        //     'selection_mode' => $this->selection_mode,
        //     'student_ids' => $this->student_ids,
        //     'level_id' => $this->level_id,
        //     'bill_category_id' => $this->bill_category_id,
        //     'title' => $this->title,
        //     'amount' => $this->amount,
        //     'due_date' => $this->due_date,
        // ]);

        \Log::info('Bills: validateAndConfirm called', [
            'selection_mode' => $this->selection_mode,
            'student_ids' => $this->student_ids,
            'level_id' => $this->level_id,
            'bill_category_id' => $this->bill_category_id,
            'title' => $this->title,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
        ]);

        $rules = [
            'selection_mode' => 'required|in:individual,level,all',
            'bill_category_id' => 'required|exists:bill_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date|after:today',
        ];

        // Add conditional validation based on selection mode
        if ($this->selection_mode === 'individual') {
            $rules['student_ids'] = 'required|array|min:1';
            $rules['student_ids.*'] = 'exists:students,id';
        } elseif ($this->selection_mode === 'level') {
            $rules['level_id'] = 'required|exists:levels,id';
        }

        \Log::info('Bills: Validating with rules', ['rules' => array_keys($rules)]);

        try {
            $this->validate($rules);
            \Log::info('Bills: Validation passed');
            $this->showConfirmation = true;
        } catch (\Exception $e) {
            \Log::error('Bills: Validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function backToForm()
    {
        $this->showConfirmation = false;
    }

    public function save()
    {
        \Log::info('Bills: save() called');

        try {
            DB::transaction(function () {
                $user = auth()->user();
                $schoolId = $user->school_id;

                \Log::info('Bills: Getting target students', [
                    'selection_mode' => $this->selection_mode,
                    'school_id' => $schoolId
                ]);

                // Get target students based on selection mode
                $targetStudents = match ($this->selection_mode) {
                    'individual' => Student::whereIn('id', $this->student_ids)->get(),
                    'level' => Student::where('level_id', $this->level_id)->where('status', 'ACTIVE')->get(),
                    'all' => Student::where('school_id', $schoolId)->where('status', 'ACTIVE')->get(),
                };

                \Log::info('Bills: Target students count', ['count' => $targetStudents->count()]);

                $createdCount = 0;
                foreach ($targetStudents as $student) {
                    Bill::create([
                        'school_id' => $schoolId,
                        'invoice_code' => 'INV-BILL-' . time() . '-' . rand(1000, 9999),
                        'student_id' => $student->id,
                        'bill_category_id' => $this->bill_category_id,
                        'title' => $this->title,
                        'amount' => $this->amount,
                        'payment_fee' => 0,
                        'admin_fee' => 0,
                        'status' => 'UNPAID',
                        'due_date' => $this->due_date,
                        'paid_at' => null,
                        'payment_method' => null,
                    ]);
                    $createdCount++;
                }

                \Log::info('Bills: Created successfully', ['count' => $createdCount]);

                Flux::toast(variant: 'success', heading: 'Berhasil', text: "{$createdCount} tagihan berhasil dibuat.");
            });

            $this->reset(['selection_mode', 'student_ids', 'level_id', 'bill_category_id', 'title', 'amount', 'due_date', 'showConfirmation']);
            $this->modal('bill-create-modal')->close();
        } catch (\Exception $e) {
            \Log::error('Bills: save() failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Flux::toast(variant: 'danger', heading: 'Error', text: 'Gagal membuat tagihan: ' . $e->getMessage());
        }
    }

    public function testAction()
    {
        dd('TEST BUTTON CLICKED - Livewire is working!');
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'PAID' => 'green',
            'UNPAID' => 'orange',
            'CANCELLED' => 'red',
            default => 'zinc',
        };
    }

    public function getSelectedStudentsCount()
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        return match ($this->selection_mode) {
            'individual' => count($this->student_ids),
            'level' => Student::where('level_id', $this->level_id)->where('status', 'ACTIVE')->count(),
            'all' => Student::where('school_id', $schoolId)->where('status', 'ACTIVE')->count(),
            default => 0,
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Tagihan') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola tagihan santri') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Buat Tagihan') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari invoice/judul..." />

        <flux:select wire:model.live="studentFilter" variant="listbox" placeholder="Filter Santri" clearable>
            <flux:select.option value="">Semua Santri</flux:select.option>
            @foreach ($students as $student)
                <flux:select.option value="{{ $student->id }}">{{ $student->full_name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="categoryFilter" variant="listbox" placeholder="Filter Kategori" clearable>
            <flux:select.option value="">Semua Kategori</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter Status" clearable>
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="PAID">Lunas</flux:select.option>
            <flux:select.option value="UNPAID">Belum Lunas</flux:select.option>
            <flux:select.option value="CANCELLED">Dibatalkan</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$bills">
        <flux:table.columns>
            <flux:table.column>Invoice</flux:table.column>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Judul</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Jatuh Tempo</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($bills as $bill)
                        <flux:table.row :key="$bill->id">
                            <flux:table.cell class="font-mono text-sm">
                                {{ $bill->invoice_code }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    @if($bill->student)
                                        <flux:avatar size="sm" :name="$bill->student->full_name" />
                                        <span class="font-medium">{{ $bill->student->full_name }}</span>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($bill->category)
                                    <flux:badge size="sm" color="blue">{{ $bill->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>{{ $bill->title }}</flux:table.cell>

                            <flux:table.cell>
                                <span class="text-zinc-700 dark:text-zinc-300">
                                    Rp {{ number_format($bill->amount, 0, ',', '.') }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell class="text-zinc-500">
                                {{ $bill->due_date->format('d M Y') }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->getStatusColor($bill->status)">
                                    {{ match ($bill->status) {
                    'PAID' => 'Lunas',
                    'UNPAID' => 'Belum Lunas',
                    'CANCELLED' => 'Dibatalkan',
                    default => $bill->status
                } }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button variant="ghost" size="sm" icon="eye" wire:click="viewDetail('{{ $bill->id }}')">
                                    Detail
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.document-text class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada tagihan</flux:heading>
                                <flux:subheading>Belum ada data tagihan. Klik "Buat Tagihan" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Create Flyout -->
    <flux:modal name="bill-create-modal" flyout class="space-y-6">
        @if(!$showConfirmation)
            <!-- Form Step -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Buat Tagihan Baru</flux:heading>
                    <flux:subheading>Isi data tagihan dan pilih santri penerima</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-8">
                    <!-- Selection Mode -->
                    <div>
                        <flux:label class="mb-4">Pilih Penerima Tagihan</flux:label>
                        <flux:radio.group wire:model.live="selection_mode" class="space-y-3">
                            <flux:radio value="individual" label="Pilih Santri Tertentu"
                                description="Pilih 1 atau lebih santri" />
                            <flux:radio value="level" label="Pilih Berdasarkan Kelas"
                                description="Semua santri aktif di kelas tertentu" />
                            <flux:radio value="all" label="Semua Santri Aktif"
                                description="Semua santri aktif di pesantren" />
                        </flux:radio.group>
                    </div>

                    <!-- Student Selection (Individual Mode) -->
                    @if($selection_mode === 'individual')
                        <flux:select wire:model.live="student_ids" variant="listbox" multiple placeholder="Pilih santri..."
                            label="Santri">
                            @foreach($students as $student)
                                <flux:select.option value="{{ $student->id }}">
                                    {{ $student->full_name }} ({{ $student->nis }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <!-- Level Selection (Level Mode) -->
                    @if($selection_mode === 'level')
                        <flux:select wire:model.live="level_id" variant="listbox" placeholder="Pilih kelas..." label="Kelas">
                            @foreach($levels as $level)
                                <flux:select.option value="{{ $level->id }}">
                                    {{ $level->department->name ?? '' }} - {{ $level->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <!-- Info Badge for Selected Count -->
                    @if($selection_mode === 'all' || ($selection_mode === 'level' && $level_id) || ($selection_mode === 'individual' && count($student_ids) > 0))
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300 text-sm">
                                <flux:icon.information-circle class="w-4 h-4" />
                                <span>{{ $this->getSelectedStudentsCount() }} santri akan menerima tagihan ini</span>
                            </div>
                        </div>
                    @endif

                    <flux:separator variant="subtle" class="my-6" />

                    <!-- Bill Details -->
                    <div class="space-y-6">
                        <flux:select wire:model="bill_category_id" label="Kategori" variant="listbox"
                            placeholder="Pilih kategori">
                            @foreach ($categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="title" label="Judul Tagihan"
                            placeholder="Contoh: SPP Bulan Februari 2026" />

                        <flux:input wire:model="amount" type="number" label="Jumlah (Rp)" placeholder="0" />

                        <flux:input wire:model="due_date" type="date" label="Jatuh Tempo" />
                    </div>

                    <div class="flex gap-2 pt-4">
                        <flux:spacer />

                        <!-- DEBUG: Show any validation errors -->
                        @if($errors->any())
                            <div class="text-red-500 text-sm">
                                Errors: {{ implode(', ', $errors->all()) }}
                            </div>
                        @endif

                        <flux:modal.close>
                            <flux:button variant="ghost">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" wire:click="validateAndConfirm">Lanjut ke Konfirmasi</flux:button>
                    </div>
                </div>
            </div>
        @else
            <!-- Confirmation Step -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Konfirmasi Tagihan</flux:heading>
                    <flux:subheading>Pastikan data berikut sudah benar sebelum diproses</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-6">
                    <!-- Summary Card -->
                    <div
                        class="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <flux:icon.banknotes class="w-24 h-24" />
                        </div>

                        <div class="relative z-10 space-y-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Total Penerima
                                    </div>
                                    <div
                                        class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                        {{ $this->getSelectedStudentsCount() }}
                                        <span class="text-sm font-normal text-zinc-500">Santri</span>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-1">
                                        @if($selection_mode === 'individual')
                                            Mode: Pilihan Manual
                                        @elseif($selection_mode === 'level')
                                            Mode: Per Kelas ({{ $levels->firstWhere('id', $level_id)?->name ?? '-' }})
                                        @else
                                            Mode: Semua Santri
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Total Estimasi
                                    </div>
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        Rp {{ number_format($amount * $this->getSelectedStudentsCount(), 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            <flux:separator variant="subtle" />

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Judul
                                        Tagihan</div>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $title }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Kategori
                                    </div>
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ $categories->firstWhere('id', $bill_category_id)?->name ?? '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Nominal per
                                        Santri</div>
                                    <div class="font-medium text-zinc-900 dark:text-white">Rp
                                        {{ number_format($amount, 0, ',', '.') }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Jatuh Tempo
                                    </div>
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($due_date)->format('d M Y') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Alert -->
                    <div
                        class="flex gap-4 p-4 bg-orange-50 dark:bg-orange-900/20 text-orange-800 dark:text-orange-200 rounded-lg border border-orange-200 dark:border-orange-800/30">
                        <flux:icon.exclamation-triangle class="w-5 h-5 flex-shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <p class="font-medium mb-1">Perhatian</p>
                            <p class="opacity-90">
                                Anda akan membuat <strong>{{ $this->getSelectedStudentsCount() }} tagihan baru</strong>.
                                Pastikan data sudah benar karena tagihan yang sudah dibuat akan langsung muncul di akun wali
                                santri.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <flux:button variant="ghost" wire:click="backToForm">Kembali & Edit</flux:button>
                    <flux:spacer />
                    <flux:button variant="primary" wire:click="save">
                        Proses Tagihan
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>


    <!-- Detail Flyout -->
    <flux:modal name="bill-detail-modal" flyout class="space-y-6">
        @if($selectedBill)
                <div class="space-y-6">
                    <!-- Header -->
                    <div>
                        <flux:heading size="lg">Detail Tagihan</flux:heading>
                        <flux:subheading class="font-mono">{{ $selectedBill->invoice_code }}</flux:subheading>
                    </div>

                    <!-- Status Badge -->
                    <div>
                        <flux:badge :color="$this->getStatusColor($selectedBill->status)">
                            {{ match ($selectedBill->status) {
                'PAID' => 'Lunas',
                'UNPAID' => 'Belum Lunas',
                'CANCELLED' => 'Dibatalkan',
                default => $selectedBill->status
            } }}
                        </flux:badge>
                    </div>

                    <flux:separator />

                    <!-- Student Info -->
                    @if($selectedBill->student)
                        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
                            <flux:label class="mb-3">Santri</flux:label>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="lg" :name="$selectedBill->student->full_name" />
                                <div>
                                    <div class="font-semibold text-lg">{{ $selectedBill->student->full_name }}</div>
                                    <div class="text-sm text-zinc-500">NIS: {{ $selectedBill->student->nis ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Amount Card -->
                    <div
                        class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-lg p-6">
                        <flux:label class="mb-3">Total Tagihan</flux:label>
                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                            Rp {{ number_format($selectedBill->amount, 0, ',', '.') }}
                        </div>
                    </div>

                    <!-- Bill Details -->
                    <div class="space-y-4">
                        <div>
                            <flux:label class="mb-2">Kategori</flux:label>
                            @if($selectedBill->category)
                                <flux:badge size="sm" color="blue">{{ $selectedBill->category->name }}</flux:badge>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </div>

                        <div>
                            <flux:label class="mb-2">Judul</flux:label>
                            <p class="text-zinc-700 dark:text-zinc-300">{{ $selectedBill->title }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:label class="mb-2">Jatuh Tempo</flux:label>
                                <div class="text-zinc-700 dark:text-zinc-300">{{ $selectedBill->due_date->format('d M Y') }}
                                </div>
                            </div>

                            @if($selectedBill->paid_at)
                                <div>
                                    <flux:label class="mb-2">Dibayar Pada</flux:label>
                                    <div class="text-zinc-700 dark:text-zinc-300">{{ $selectedBill->paid_at->format('d M Y, H:i') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if($selectedBill->payment_method)
                            <div>
                                <flux:label class="mb-2">Metode Pembayaran</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:icon.{{ $selectedBill->payment_method === 'cash' ? 'banknotes' : 'credit-card' }}
                                        class="w-4 h-4 text-zinc-500" />
                                    <span>{{ $selectedBill->payment_method === 'cash' ? 'Tunai' : 'Payment Gateway' }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <flux:separator />

                    <!-- Timestamps -->
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <flux:label class="mb-2">Dibuat</flux:label>
                            <div class="text-zinc-600 dark:text-zinc-400">{{ $selectedBill->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                        <div>
                            <flux:label class="mb-2">Terakhir Diubah</flux:label>
                            <div class="text-zinc-600 dark:text-zinc-400">{{ $selectedBill->updated_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <flux:button variant="ghost" wire:click="closeDetail">Tutup</flux:button>
                    </div>
                </div>
        @endif
    </flux:modal>
</div>