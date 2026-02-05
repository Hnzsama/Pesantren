<?php

use App\Models\Student;
use App\Models\WalletTransaction;
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
    public $typeFilter = '';

    #[Url]
    public $statusFilter = '';

    public $selectedTransaction = null;

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
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Transaksi Dompet') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Riwayat transaksi dompet santri') }}</flux:subheading>
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
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Platform Fee</flux:table.column>
            <flux:table.column>PG Fee</flux:table.column>
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
                                    @if($txn->student)
                                        <flux:avatar size="sm" :name="$txn->student->full_name" />
                                        <span class="font-medium">{{ $txn->student->full_name }}</span>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
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
                                <span class="text-zinc-500 text-sm">
                                    {{ $txn->admin_fee > 0 ? 'Rp ' . number_format($txn->admin_fee, 0, ',', '.') : '-' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-zinc-500 text-sm">
                                    {{ $txn->payment_fee > 0 ? 'Rp ' . number_format($txn->payment_fee, 0, ',', '.') : '-' }}
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
                                <flux:button variant="ghost" size="sm" icon="eye" wire:click="viewDetail('{{ $txn->id }}')">
                                    Detail
                                </flux:button>
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
                    @if($selectedTransaction->student)
                        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
                            <flux:label class="mb-3">Santri</flux:label>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="lg" :name="$selectedTransaction->student->full_name" />
                                <div>
                                    <div class="font-semibold text-lg">{{ $selectedTransaction->student->full_name }}</div>
                                    <div class="text-sm text-zinc-500">NIS: {{ $selectedTransaction->student->nis ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

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

                    <!-- Fees Breakdown -->
                    @if($selectedTransaction->payment_fee > 0 || $selectedTransaction->admin_fee > 0)
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-5 space-y-4">
                            <flux:label>Rincian Biaya</flux:label>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-zinc-600 dark:text-zinc-400">Jumlah Transaksi</span>
                                    <span class="font-medium">Rp
                                        {{ number_format($selectedTransaction->amount, 0, ',', '.') }}</span>
                                </div>

                                @if($selectedTransaction->payment_fee > 0)
                                    <div class="flex justify-between items-center">
                                        <span class="text-zinc-600 dark:text-zinc-400">Biaya Payment Gateway</span>
                                        <span class="font-medium">Rp
                                            {{ number_format($selectedTransaction->payment_fee, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @if($selectedTransaction->admin_fee > 0)
                                    <div class="flex justify-between items-center">
                                        <span class="text-zinc-600 dark:text-zinc-400">Biaya Admin</span>
                                        <span class="font-medium">Rp
                                            {{ number_format($selectedTransaction->admin_fee, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                <flux:separator variant="subtle" />

                                <div class="flex justify-between items-center text-base pt-1">
                                    <span class="font-semibold">Total Keseluruhan</span>
                                    <span class="font-bold text-lg">Rp
                                        {{ number_format($selectedTransaction->amount + $selectedTransaction->payment_fee + $selectedTransaction->admin_fee, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

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
                                    <span>{{ $selectedTransaction->payment_method === 'cash' ? 'Tunai' : 'Payment Gateway' }}</span>
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
</div>