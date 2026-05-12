<?php

use App\Models\Student;
use App\Models\WalletTransaction;
use App\Services\TransactionService;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new #[Lazy] class extends Component {
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
                <div class="flex gap-2">
                    <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-700 rounded-lg animate-pulse"></div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                @foreach(range(1, 5) as $i)
                    <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                @endforeach
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 10) as $i)
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded bg-zinc-100 dark:bg-zinc-800 animate-pulse"></div>
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
    public $search = '';

    #[Url]
    public $studentFilter = '';

    #[Url]
    public $categoryFilter = '';

    #[Url]
    public $typeFilter = '';

    #[Url]
    public $statusFilter = '';

    public $selectedTransaction = null;

    // Export Form
    public $exportCategory = '';

    // Topup Form
    public $topup_student_id = '';
    public $topup_amount = 0;
    public $topup_admin_fee = 2000;
    public $topup_description = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = WalletTransaction::query()
            ->with(['student', 'pic'])
            ->when($schoolId, fn($q) => $q->where('wallet_transactions.school_id', $schoolId))
            ->when($this->studentFilter, fn($q) => $q->where('student_id', $this->studentFilter))
            ->when($this->categoryFilter, fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn($q) => $q->where(function ($query) {
                $query->where('invoice_code', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            }))
            ->orderBy('created_at', 'desc');

        $students = Student::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('full_name')
            ->get();

        return [
            'transactions' => $query->paginate(15),
            'students' => $students,
        ];
    }

    public function viewDetail($id)
    {
        $this->selectedTransaction = WalletTransaction::with(['student', 'pic'])->find($id);
        $this->modal('transaction-detail-modal')->show();
    }

    public function closeDetail()
    {
        $this->selectedTransaction = null;
        $this->modal('transaction-detail-modal')->close();
    }

    public function openTopupModal()
    {
        $this->reset(['topup_student_id', 'topup_amount', 'topup_description']);
        $this->topup_admin_fee = 2000; // Default fee
        $this->modal('manual-topup-modal')->show();
    }

    public function createTopup(TransactionService $transactionService)
    {
        $this->validate([
            'topup_student_id' => 'required|exists:students,id',
            'topup_amount' => 'required|numeric|min:1000',
            'topup_admin_fee' => 'required|numeric|min:0',
        ]);

        try {
            $student = Student::findOrFail($this->topup_student_id);
            $transactionService->processTopup(
                $student, 
                (float) $this->topup_amount, 
                auth()->user(), 
                (float) $this->topup_admin_fee,
                $this->topup_description
            );

            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Top up manual berhasil diproses.');
            $this->modal('manual-topup-modal')->close();
            $this->resetPage();
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }

    public function openExportModal()
    {
        $this->exportCategory = '';
        $this->modal('export-modal')->show();
    }

    public function runExport()
    {
        $url = route('wallet-transactions.export', ['category' => $this->exportCategory]);
        $this->modal('export-modal')->close();
        return redirect()->away($url);
    }

    public function runReport()
    {
        $url = route('wallet-transactions.report', ['category' => $this->exportCategory]);
        $this->modal('export-modal')->close();
        return redirect()->away($url);
    }

    public function getCategoryColor($category)
    {
        return match ($category) {
            'TOPUP' => 'green',
            'WITHDRAWAL' => 'orange',
            'PURCHASE' => 'blue',
            'DEPOSIT_TO_HQ' => 'purple',
            default => 'zinc',
        };
    }

    public function getTypeColor($type)
    {
        return match ($type) {
            'CREDIT' => 'green',
            'DEBIT' => 'red',
            default => 'zinc',
        };
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
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Transaksi Dompet') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Riwayat transaksi dompet santri') }}</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button variant="subtle" icon="document-arrow-down" wire:click="openExportModal">Ekspor CSV</flux:button>
                <flux:button variant="primary" icon="plus" wire:click="openTopupModal">Top Up Manual</flux:button>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari invoice/deskripsi..." />

        <flux:select wire:model.live="studentFilter" variant="listbox" placeholder="Filter Santri" clearable>
            <flux:select.option value="">Semua Santri</flux:select.option>
            @foreach ($students as $student)
                <flux:select.option value="{{ $student->id }}">{{ $student->full_name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="categoryFilter" variant="listbox" placeholder="Filter Kategori" clearable>
            <flux:select.option value="">Semua Kategori</flux:select.option>
            <flux:select.option value="TOPUP">Top Up</flux:select.option>
            <flux:select.option value="WITHDRAWAL">Penarikan</flux:select.option>
            <flux:select.option value="PURCHASE">Pembelian</flux:select.option>
            <flux:select.option value="DEPOSIT_TO_HQ">Setor ke Pusat</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="typeFilter" variant="listbox" placeholder="Filter Tipe" clearable>
            <flux:select.option value="">Semua Tipe</flux:select.option>
            <flux:select.option value="CREDIT">Kredit (Masuk)</flux:select.option>
            <flux:select.option value="DEBIT">Debit (Keluar)</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter Status" clearable>
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="PAID">Lunas</flux:select.option>
            <flux:select.option value="UNPAID">Belum Lunas</flux:select.option>
            <flux:select.option value="CANCELLED">Dibatalkan</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$transactions">
        <flux:table.columns>
            <flux:table.column>Invoice</flux:table.column>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Metode</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($transactions as $txn)
                        <flux:table.row :key="$txn->id">
                            <flux:table.cell class="font-mono text-sm">
                                {{ $txn->invoice_code }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="sm" :name="$txn->student_name ?? $txn->student?->full_name ?? '-'" />
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $txn->student_name ?? $txn->student?->full_name ?? '-' }}</span>
                                        <span class="text-xs text-zinc-500">{{ $txn->student_nis ?? $txn->student?->nis ?? '-' }}</span>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->getCategoryColor($txn->category)">
                                    {{ match ($txn->category) {
                    'TOPUP' => 'Top Up',
                    'WITHDRAWAL' => 'Penarikan',
                    'PURCHASE' => 'Pembelian',
                    'DEPOSIT_TO_HQ' => 'Setor ke Pusat',
                    default => $txn->category
                } }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$txn->payment_method === 'cash' ? 'blue' : 'purple'" variant="subtle">
                                    {{ $txn->payment_method === 'cash' ? 'Tunai' : 'Non-Tunai' }}
                                </flux:badge>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->getTypeColor($txn->type)" variant="outline">
                                    {{ $txn->type === 'CREDIT' ? '↑ Kredit' : '↓ Debit' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="{{ $txn->type === 'CREDIT' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $txn->type === 'CREDIT' ? '+' : '-' }} Rp {{ number_format($txn->amount, 0, ',', '.') }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->getStatusColor($txn->status)">
                                    {{ match ($txn->status) {
                    'PAID' => 'Lunas',
                    'UNPAID' => 'Belum Lunas',
                    'CANCELLED' => 'Dibatalkan',
                    default => $txn->status
                } }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="text-zinc-500">
                                {{ $txn->created_at->format('d M Y, H:i') }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button variant="ghost" size="sm" icon="eye" wire:click="viewDetail('{{ $txn->id }}')">
                                        Detail
                                    </flux:button>
                                    @if($txn->category === 'TOPUP')
                                        <flux:button variant="ghost" size="sm" icon="printer" href="{{ route('wallet-transactions.print', $txn->invoice_code) }}" target="_blank" tooltip="Cetak Struk" />
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.banknotes class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada transaksi</flux:heading>
                                <flux:subheading>Belum ada transaksi dompet.</flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Detail Modal (Flyout) - Redesigned -->
    <flux:modal name="transaction-detail-modal" flyout class="space-y-6">
        @if($selectedTransaction)
                <div class="space-y-6">
                    <!-- Header -->
                    <div>
                        <flux:heading size="lg">Detail Transaksi</flux:heading>
                        <flux:subheading class="font-mono">{{ $selectedTransaction->invoice_code }}</flux:subheading>
                    </div>

                    <!-- Status Row -->
                    <div class="flex gap-2">
                        <flux:badge :color="$this->getCategoryColor($selectedTransaction->category)">
                            {{ match ($selectedTransaction->category) {
                'TOPUP' => 'Top Up',
                'WITHDRAWAL' => 'Penarikan',
                'PURCHASE' => 'Pembelian',
                'DEPOSIT_TO_HQ' => 'Setor ke Pusat',
                default => $selectedTransaction->category
            } }}
                        </flux:badge>

                        <flux:badge :color="$this->getTypeColor($selectedTransaction->type)" variant="outline">
                            {{ $selectedTransaction->type === 'CREDIT' ? '↑ Kredit (Masuk)' : '↓ Debit (Keluar)' }}
                        </flux:badge>

                        <flux:badge :color="$this->getStatusColor($selectedTransaction->status)">
                            {{ match ($selectedTransaction->status) {
                'PAID' => 'Lunas',
                'UNPAID' => 'Belum Lunas',
                'CANCELLED' => 'Dibatalkan',
                default => $selectedTransaction->status
            } }}
                        </flux:badge>
                    </div>

                    <flux:separator />

                    <!-- Student Info Card -->
                    <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
                        <flux:label class="mb-3">Santri</flux:label>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="lg" :name="$selectedTransaction->student_name ?? $selectedTransaction->student?->full_name ?? '-'" />
                            <div>
                                <div class="font-semibold text-lg">{{ $selectedTransaction->student_name ?? $selectedTransaction->student?->full_name ?? '-' }}</div>
                                <div class="text-sm text-zinc-500">NIS: {{ $selectedTransaction->student_nis ?? $selectedTransaction->student?->nis ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Card -->
                    <div
                        class="bg-gradient-to-br {{ $selectedTransaction->type === 'CREDIT' ? 'from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20' : 'from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20' }} rounded-lg p-6">
                        <flux:label class="mb-3">Jumlah Transaksi</flux:label>
                        <div
                            class="text-3xl font-bold {{ $selectedTransaction->type === 'CREDIT' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $selectedTransaction->type === 'CREDIT' ? '+' : '-' }} Rp
                            {{ number_format($selectedTransaction->amount, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Hidden Fee Details for Admin --}}

                    <flux:separator />

                    <!-- Transaction Details -->
                    <div class="space-y-4">
                        <div>
                            <flux:label class="mb-2">Deskripsi</flux:label>
                            <p class="text-zinc-700 dark:text-zinc-300">{{ $selectedTransaction->description }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:label class="mb-2">Metode Pembayaran</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:icon.{{ $selectedTransaction->payment_method === 'cash' ? 'banknotes' : 'credit-card' }}
                                        class="w-4 h-4 text-zinc-500" />
                                    <span>{{ $selectedTransaction->payment_method === 'cash' ? 'Tunai' : ($selectedTransaction->payment_method === 'transfer' ? 'Transfer' : 'Non-Tunai') }}</span>
                                </div>
                            </div>

                            @if($selectedTransaction->pic)
                                <div>
                                    <flux:label class="mb-2">Penanggung Jawab</flux:label>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$selectedTransaction->pic->name" />
                                        <span class="text-sm font-medium">{{ $selectedTransaction->pic->name }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <flux:separator />

                    <!-- Timestamps -->
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <flux:label class="mb-2">Dibuat</flux:label>
                            <div class="text-zinc-600 dark:text-zinc-400">
                                {{ $selectedTransaction->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                        <div>
                            <flux:label class="mb-2">Terakhir Diubah</flux:label>
                            <div class="text-zinc-600 dark:text-zinc-400">
                                {{ $selectedTransaction->updated_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <flux:button variant="ghost" wire:click="closeDetail">Tutup</flux:button>
                    </div>
                </div>
        @endif
    </flux:modal>

    <!-- Manual Topup Modal -->
    <flux:modal name="manual-topup-modal" class="md:w-[500px] space-y-6">
        <div>
            <flux:heading size="lg">Top Up Manual (Tunai)</flux:heading>
            <flux:subheading>Gunakan fitur ini untuk menerima top up tunai dari orang tua santri.</flux:subheading>
        </div>

        <form wire:submit="createTopup" class="space-y-4">
            <flux:select wire:model="topup_student_id" label="Pilih Santri" variant="listbox" filter placeholder="Cari santri...">
                @foreach ($students as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="topup_amount" type="number" label="Nominal Top Up" icon="banknotes" prefix="Rp" />
                <flux:input wire:model="topup_admin_fee" type="number" label="Biaya Admin" icon="receipt-percent" prefix="Rp" />
            </div>

            <flux:textarea wire:model="topup_description" label="Keterangan (Opsional)" placeholder="Contoh: Titipan tunai di kantor" />

            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg">
                <div class="flex justify-between items-center text-sm mb-1">
                    <span class="text-zinc-500">Saldo Masuk</span>
                    <span class="font-medium text-green-600">Rp {{ number_format((float)($topup_amount ?: 0), 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center text-sm mb-2">
                    <span class="text-zinc-500">Biaya Admin</span>
                    <span class="font-medium text-red-600">Rp {{ number_format((float)($topup_admin_fee ?: 0), 0, ',', '.') }}</span>
                </div>
                <flux:separator variant="subtle" />
                <div class="flex justify-between items-center pt-2">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">Total Dibayar Orang Tua</span>
                    <span class="font-bold text-lg text-zinc-900 dark:text-zinc-100">
                        Rp {{ number_format((float)($topup_amount ?: 0) + (float)($topup_admin_fee ?: 0), 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Proses Top Up</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Export Modal -->
    <flux:modal name="export-modal" class="md:w-[400px] space-y-6">
        <div>
            <flux:heading size="lg">Ekspor Transaksi</flux:heading>
            <flux:subheading>Pilih kategori transaksi yang ingin diekspor ke format CSV.</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:select wire:model="exportCategory" label="Pilih Kategori" variant="listbox">
                <flux:select.option value="">Semua Kategori</flux:select.option>
                <flux:select.option value="TOPUP">Top Up</flux:select.option>
                <flux:select.option value="WITHDRAWAL">Penarikan</flux:select.option>
                <flux:select.option value="PURCHASE">Pembelian</flux:select.option>
                <flux:select.option value="DEPOSIT_TO_HQ">Setor ke Pusat</flux:select.option>
            </flux:select>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <div class="flex gap-2">
                    <flux:button wire:click="runExport" variant="subtle" icon="document-text">Unduh CSV</flux:button>
                    <flux:button wire:click="runReport" variant="primary" icon="printer">Cetak Laporan (PDF)</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>