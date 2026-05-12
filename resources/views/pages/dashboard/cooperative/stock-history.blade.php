<?php

use App\Models\ProductMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-8 w-full animate-pulse">
            <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
            <div class="h-96 bg-zinc-50 dark:bg-zinc-900 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
        </div>
        HTML;
    }

    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $typeFilter = '';

    public function with(): array
    {
        $query = ProductMovement::with(['product', 'pic'])
            ->where('school_id', auth()->user()->school_id)
            ->latest();

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        return [
            'movements' => $query->paginate(20),
        ];
    }
}; ?>

<div class="w-full">
    <div class="mb-6">
        <flux:heading size="xl" level="1">{{ __('Riwayat Pergerakan Stok') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Log lengkap perubahan stok barang koperasi') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:input wire:model.live="search" label="Cari Produk" placeholder="Nama atau SKU..." icon="magnifying-glass"
            clearable />
        <flux:select wire:model.live="typeFilter" label="Tipe Perubahan">
            <flux:select.option value="">Semua</flux:select.option>
            <flux:select.option value="IN">Masuk (IN)</flux:select.option>
            <flux:select.option value="OUT">Keluar (OUT)</flux:select.option>
            <flux:select.option value="ADJUSTMENT">Koreksi (ADJUSTMENT)</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$movements">
        <flux:table.columns>
            <flux:table.column>Waktu</flux:table.column>
            <flux:table.column>Produk</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Alur Stok</flux:table.column>
            <flux:table.column>Sumber</flux:table.column>
            <flux:table.column>Catatan</flux:table.column>
            <flux:table.column>PIC</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($movements as $movement)
                <flux:table.row :key="$movement->id">
                    <flux:table.cell class="whitespace-nowrap">
                        <div class="flex flex-col text-sm">
                            <span>{{ $movement->created_at->format('d/m/Y') }}</span>
                            <span class="text-xs text-zinc-500">{{ $movement->created_at->format('H:i') }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-sm">{{ $movement->product_name }}</span>
                            <span class="text-xs text-zinc-500">{{ $movement->product_sku ?? '-' }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm"
                            :variant="$movement->type === 'IN' ? 'success' : ($movement->type === 'OUT' ? 'danger' : 'neutral')">
                            {{ $movement->type }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="font-bold">
                        {{ $movement->type === 'IN' ? '+' : ($movement->type === 'OUT' ? '-' : '') }}{{ $movement->quantity }}
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">
                        {{ $movement->stock_before }} → {{ $movement->stock_after }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" variant="outline">{{ $movement->source }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm italic">
                        {{ $movement->note ?? '-' }}
                        @if ($movement->reference_id)
                            <div class="text-xs text-zinc-400">Ref: {{ $movement->reference_id }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-sm">
                        {{ $movement->pic->name }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>