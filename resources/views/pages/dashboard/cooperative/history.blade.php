<?php

use App\Models\WalletTransaction;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

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
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                @foreach(range(1, 4) as $i)
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
    public $dateFrom = '';

    #[Url]
    public $dateTo = '';

    #[Url]
    public $paymentMethodFilter = '';

    public $exportFilter = 'today';
    public $exportDateFrom = '';
    public $exportDateTo = '';


    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $user = auth()->user();
        $isCashier = $user->hasRole('kasir');

        $query = WalletTransaction::with(['student', 'operator', 'items'])
            ->where('school_id', $schoolId)
            ->where('category', 'PURCHASE')
            ->when($isCashier, fn($q) => $q->where('operator_id', $user->id))
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('invoice_code', 'like', '%' . $this->search . '%');
        }

        if ($this->studentFilter) {
            $query->whereHas('student', function ($q) {
                $q->where('full_name', 'like', '%' . $this->studentFilter . '%')
                    ->orWhere('nis', 'like', '%' . $this->studentFilter . '%')
                    ->orWhereHas('card', function ($cq) {
                        $cq->where('card_number', 'like', '%' . $this->studentFilter . '%');
                    });
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->paymentMethodFilter) {
            $query->where('payment_method', $this->paymentMethodFilter);
        }

        return [
            'transactions' => $query->paginate(15),
        ];
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Riwayat Pembelian') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">
                    {{ __('Daftar lengkap transaksi belanja santri di koperasi') }}
                </flux:subheading>
            </div>
            @role('kasir')
            <flux:modal.trigger name="export-report-modal">
                <flux:button variant="primary" icon="printer">Cetak Laporan</flux:button>
            </flux:modal.trigger>
            @endrole
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <flux:input wire:model.live="search" label="Cari Invoice" placeholder="POS-..." icon="magnifying-glass"
            clearable />
        <flux:input wire:model.live="studentFilter" label="Cari Santri" placeholder="Nama / NIS / Kartu" icon="user"
            clearable />
        <flux:input wire:model.live="dateFrom" type="date" label="Dari Tanggal" clearable />
        <flux:input wire:model.live="dateTo" type="date" label="Sampai Tanggal" clearable />
        <flux:select wire:model.live="paymentMethodFilter" label="Metode" placeholder="Semua Metode" clearable>
            <flux:select.option value="cash">Tunai</flux:select.option>
            <flux:select.option value="wallet">Dompet (Wallet)</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$transactions">
        <flux:table.columns>
            <flux:table.column>Waktu</flux:table.column>
            <flux:table.column>Invoice</flux:table.column>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Barang/Jasa</flux:table.column>
            <flux:table.column>Nominal</flux:table.column>
            <flux:table.column>Metode</flux:table.column>
            <flux:table.column>Operator</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($transactions as $transaction)
                <flux:table.row :key="$transaction->id">
                    <flux:table.cell class="whitespace-nowrap">
                        <div class="flex flex-col">
                            <span>{{ $transaction->created_at->format('d/m/Y') }}</span>
                            <span class="text-xs text-zinc-500">{{ $transaction->created_at->format('H:i') }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-sm uppercase">
                        {{ $transaction->invoice_code }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            @if($transaction->student)
                                <span class="font-medium">{{ $transaction->student->full_name }}</span>
                                <span class="text-xs text-zinc-500">{{ $transaction->student->nis }}</span>
                            @else
                                <span class="font-medium text-zinc-400">PELANGGAN UMUM</span>
                                <span class="text-[10px] text-zinc-400 opacity-60 italic">Guest Checkout</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($transaction->items->isNotEmpty())
                            <div class="space-y-1">
                                @foreach($transaction->items->take(2) as $item)
                                    <div class="text-xs font-medium uppercase truncate max-w-[150px]">{{ $item->product_name }}</div>
                                @endforeach
                                @if($transaction->items->count() > 2)
                                    <div class="text-[10px] text-zinc-400">+{{ $transaction->items->count() - 2 }} item lainnya</div>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-zinc-400 italic">Data tidak tersedia</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-bold text-red-600 dark:text-red-400">
                        -Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($transaction->payment_method === 'cash')
                            <flux:badge size="sm" color="blue" inset="top bottom">TUNAI</flux:badge>
                        @else
                            <flux:badge size="sm" color="green" inset="top bottom">DOMPET</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" variant="outline" icon="user-circle">
                            {{ $transaction->operator->name ?? 'System' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="flex items-center gap-1">
                        <flux:button variant="ghost" size="sm" icon="printer" 
                            x-on:click="window.open('{{ route('cooperative.print-receipt', ['invoiceCode' => $transaction->invoice_code]) }}', '_blank', 'width=400,height=600')" />
                        <flux:modal.trigger name="detail-{{ $transaction->id }}">
                            <flux:button variant="ghost" size="sm" icon="eye" />
                        </flux:modal.trigger>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-10 text-zinc-500">
                        Tidak ada riwayat transaksi yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Detail Modals -->
    @foreach ($transactions as $transaction)
        <flux:modal name="detail-{{ $transaction->id }}" class="w-full max-w-lg">
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:heading size="lg">Detail Transaksi</flux:heading>
                        <flux:subheading>{{ $transaction->invoice_code }}</flux:subheading>
                    </div>
                    <flux:badge variant="success" size="sm">PAID</flux:badge>
                </div>

                <div class="space-y-4 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
                    <div class="flex justify-between">
                        <span class="text-zinc-500 text-sm">Waktu Transaksi</span>
                        <span class="font-medium text-sm">{{ $transaction->created_at->format('d M Y H:i:s') }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-4 dark:border-zinc-800">
                        <span class="text-zinc-500 text-sm">Pelanggan</span>
                        <div class="text-right">
                            @if($transaction->student)
                                <div class="font-medium text-sm">{{ $transaction->student->full_name }}</div>
                                <div class="text-xs text-zinc-400">{{ $transaction->student->nis }}</div>
                            @else
                                <div class="font-medium text-sm text-zinc-400 italic">Pelanggan Umum</div>
                                <div class="text-[10px] text-zinc-500 uppercase tracking-widest">Tunai</div>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between border-t pt-4 dark:border-zinc-800">
                        <span class="text-zinc-500 text-sm">Nominal Pembelian</span>
                        <span class="font-bold text-red-600 dark:text-red-400">Rp
                            {{ number_format($transaction->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-4 dark:border-zinc-800">
                        <span class="text-zinc-500 text-sm">Operator Koperasi</span>
                        <span class="font-medium text-sm">{{ $transaction->operator->name ?? '-' }}</span>
                    </div>
                </div>

                @if ($transaction->items->isNotEmpty())
                    <div class="space-y-4">
                        <flux:heading size="sm" class="uppercase tracking-widest text-zinc-400">Daftar Barang</flux:heading>
                        <div class="max-h-64 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 divide-y dark:divide-zinc-800 custom-scrollbar">
                            @foreach ($transaction->items as $item)
                                <div class="p-3 flex justify-between items-center">
                                    <div class="flex-1">
                                        <div class="text-sm font-bold uppercase">{{ $item->product_name }}</div>
                                        <div class="text-[10px] text-zinc-500">{{ $item->quantity }} x Rp
                                            {{ number_format($item->price, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="text-sm font-black">
                                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-between items-center pt-4 border-t dark:border-zinc-800">
                    <flux:button variant="primary" size="sm" icon="printer"
                        x-on:click="window.open('{{ route('cooperative.print-receipt', ['invoiceCode' => $transaction->invoice_code]) }}', '_blank', 'width=400,height=600')">
                        Cetak Struk
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endforeach

    <!-- Export Modal -->
    <flux:modal name="export-report-modal" class="w-full max-w-md">
        <form action="{{ route('cooperative.export') }}" method="GET" target="_blank" class="space-y-6">
            <div>
                <flux:heading size="lg">Cetak Laporan Penjualan</flux:heading>
                <flux:subheading>Pilih rentang waktu untuk laporan penjualan kasir.</flux:subheading>
            </div>

            <flux:select name="filter" wire:model.live="exportFilter" label="Periode Waktu">
                <flux:select.option value="today">Hari Ini</flux:select.option>
                <flux:select.option value="week">Minggu Ini</flux:select.option>
                <flux:select.option value="month">Bulan Ini</flux:select.option>
                <flux:select.option value="year">Tahun Ini</flux:select.option>
                <flux:select.option value="custom">Rentang Tanggal (Kustom)</flux:select.option>
            </flux:select>

            @if($exportFilter === 'custom')
                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" name="from" wire:model="exportDateFrom" label="Dari Tanggal" required />
                    <flux:input type="date" name="to" wire:model="exportDateTo" label="Sampai Tanggal" required />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="printer">Cetak</flux:button>
            </div>
        </form>
    </flux:modal>
</div>