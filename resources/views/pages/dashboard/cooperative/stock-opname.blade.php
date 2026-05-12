<?php

use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

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

    public $note = '';

    // Active Opname Detail
    public $activeOpname = null;
    public $physicalStocks = []; // [product_id => physical_qty]

    public function with(): array
    {
        return [
            'opnames' => StockOpname::with(['pic', 'items'])
                ->where('school_id', auth()->user()->school_id)
                ->latest()
                ->paginate(15),
        ];
    }

    public function create()
    {
        $this->note = '';
        Flux::modal('create-opname-modal')->show();
    }

    public function startOpname()
    {
        $schoolId = auth()->user()->school_id;

        DB::beginTransaction();
        try {
            $opname = StockOpname::create([
                'school_id' => $schoolId,
                'opname_date' => now(),
                'status' => 'DRAFT',
                'note' => $this->note,
                'pic_user_id' => auth()->id(),
            ]);

            $products = Product::where('school_id', $schoolId)
                ->where('type', 'PRODUCT')
                ->where('is_active', true)
                ->get();

            foreach ($products as $product) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'system_stock' => $product->stock ?? 0,
                    'physical_stock' => 0,
                    'difference' => -($product->stock ?? 0),
                ]);
            }

            DB::commit();
            Flux::modal('create-opname-modal')->close();
            $this->viewDetail($opname->id);
            Flux::toast(variant: 'success', heading: 'Draf Dibuat', text: 'Silakan isi jumlah fisik stok.');
        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }

    public function viewDetail($id)
    {
        $this->activeOpname = StockOpname::with('items.product')->findOrFail($id);
        $this->physicalStocks = $this->activeOpname->items->pluck('physical_stock', 'product_id')->toArray();
        Flux::modal('detail-opname-modal')->show();
    }

    public function updatedPhysicalStocks($value, $productId)
    {
        if ($this->activeOpname && $this->activeOpname->status === 'DRAFT') {
            $item = $this->activeOpname->items->where('product_id', $productId)->first();
            if ($item) {
                $physical = (int) $value;
                $diff = $physical - $item->system_stock;

                $item->update([
                    'physical_stock' => $physical,
                    'difference' => $diff
                ]);
            }
        }
    }

    public function finalize()
    {
        if (!$this->activeOpname || $this->activeOpname->status !== 'DRAFT')
            return;

        DB::beginTransaction();
        try {
            foreach ($this->activeOpname->items as $item) {
                $product = $item->product;
                $oldStock = $product->stock;
                $newStock = $item->physical_stock;

                if ($oldStock != $newStock) {
                    // Update product stock
                    $product->update(['stock' => $newStock]);

                    // Log movement
                    ProductMovement::create([
                        'school_id' => $product->school_id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'type' => 'ADJUSTMENT',
                        'quantity' => abs($item->difference),
                        'stock_before' => $oldStock,
                        'stock_after' => $newStock,
                        'source' => 'OPNAME',
                        'reference_id' => $this->activeOpname->id,
                        'note' => 'Stok Opname #' . substr($this->activeOpname->id, -6),
                        'pic_user_id' => auth()->id(),
                    ]);
                }
            }

            $this->activeOpname->update(['status' => 'FINAL']);

            DB::commit();
            Flux::modal('detail-opname-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Stok Opname telah difinalisasi.');
        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }
}; ?>

<div class="w-full">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Stok Opname') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Audit fisik stok barang berkala') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create" class="w-full md:w-auto">{{ __('Buat Opname Baru') }}</flux:button>
    </div>

    <flux:table :paginate="$opnames">
        <flux:table.columns>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>PIC</flux:table.column>
            <flux:table.column>Total Item</flux:table.column>
            <flux:table.column>Catatan</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($opnames as $opname)
                <flux:table.row :key="$opname->id">
                    <flux:table.cell>{{ $opname->opname_date->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :variant="$opname->status === 'FINAL' ? 'success' : 'warning'">
                            {{ $opname->status }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $opname->pic->name }}</flux:table.cell>
                    <flux:table.cell>{{ $opname->items->count() }} Produk</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate text-sm">{{ $opname->note ?? '-' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button variant="ghost" size="sm" icon="eye" wire:click="viewDetail('{{ $opname->id }}')">
                            Detail
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal Create -->
    <flux:modal name="create-opname-modal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Stok Opname Baru</flux:heading>
                <flux:subheading>Sistem akan menarik seluruh data stok saat ini sebagai referensi.</flux:subheading>
            </div>

            <flux:textarea wire:model="note" label="Catatan (Opsional)"
                placeholder="Contoh: Audit akhir bulan Februari..." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="startOpname">Mulai Opname</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Detail / Fill -->
    <flux:modal name="detail-opname-modal" class="w-full max-w-4xl">
        @if ($activeOpname)
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:heading size="lg">Draf Stok Opname</flux:heading>
                        <flux:subheading>{{ $activeOpname->opname_date->format('d F Y') }} - {{ $activeOpname->note }}
                        </flux:subheading>
                    </div>
                    @if ($activeOpname->status === 'DRAFT')
                        <flux:badge variant="warning">DRAFT</flux:badge>
                    @else
                        <flux:badge variant="success">FINAL</flux:badge>
                    @endif
                </div>

                <!-- Scrollable container for the stock opname items table -->
                <div class="max-h-[550px] overflow-y-auto overflow-x-auto border border-zinc-200 dark:border-zinc-800 rounded-2xl custom-scrollbar shadow-inner bg-zinc-50/50 dark:bg-zinc-900/50 px-6 py-4">
                    <flux:table class="min-w-[700px] md:min-w-full">
                        <flux:table.columns>
                            <flux:table.column>Produk</flux:table.column>
                            <flux:table.column align="center" class="whitespace-nowrap">Stok Sistem</flux:table.column>
                            <flux:table.column align="center" class="whitespace-nowrap">Stok Fisik</flux:table.column>
                            <flux:table.column align="end" class="whitespace-nowrap">Selisih</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($activeOpname->items as $item)
                                <flux:table.row :key="$item->id" class="hover:bg-zinc-100/50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <flux:table.cell>
                                        <div class="flex flex-col min-w-[250px] py-1">
                                            <span class="font-bold text-sm text-zinc-800 dark:text-zinc-200 line-clamp-1 uppercase">{{ $item->product_name }}</span>
                                            <span class="text-[10px] font-mono text-zinc-500 tracking-wider">{{ $item->product_sku ?? '-' }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell align="center" class="font-semibold text-zinc-600 dark:text-zinc-400">
                                        {{ $item->system_stock }}
                                    </flux:table.cell>
                                    <flux:table.cell align="center">
                                        @if ($activeOpname->status === 'DRAFT')
                                            <flux:input type="number"
                                                wire:model.live.debounce.500ms="physicalStocks.{{ $item->product_id }}" 
                                                size="sm"
                                                class="w-24 text-center font-bold !bg-white dark:!bg-zinc-950 shadow-sm" />
                                        @else
                                            <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $item->physical_stock }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <div class="flex flex-col items-end">
                                            <span class="font-black text-sm {{ $item->difference < 0 ? 'text-red-500' : ($item->difference > 0 ? 'text-emerald-500' : 'text-zinc-400') }}">
                                                {{ $item->difference > 0 ? '+' : '' }}{{ $item->difference }}
                                            </span>
                                            @if($item->difference != 0)
                                                <span class="text-[9px] uppercase font-bold {{ $item->difference < 0 ? 'text-red-400/70' : 'text-emerald-400/70' }}">
                                                    {{ $item->difference < 0 ? 'Kurang' : 'Lebih' }}
                                                </span>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full md:w-auto">Tutup</flux:button>
                    </flux:modal.close>
                    @if ($activeOpname->status === 'DRAFT')
                        <flux:button variant="primary" class="w-full md:w-auto"
                            wire:confirm="Finalisasi stok opname akan mengubah seluruh stok sistem. Lanjutkan?"
                            wire:click="finalize">Finalisasi & Update Stok</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>