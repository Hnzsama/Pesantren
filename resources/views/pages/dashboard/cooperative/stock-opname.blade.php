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

    public $showCreateModal = false;
    public $showDetailModal = false;
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
        $this->showCreateModal = true;
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
                    'system_stock' => $product->stock ?? 0,
                    'physical_stock' => 0,
                    'difference' => -($product->stock ?? 0),
                ]);
            }

            DB::commit();
            $this->showCreateModal = false;
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
        $this->showDetailModal = true;
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
            $this->showDetailModal = false;
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Stok Opname telah difinalisasi.');
        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }
}; ?>

<div class="w-full">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Stok Opname') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Audit fisik stok barang berkala') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Buat Opname Baru') }}</flux:button>
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
    <flux:modal wire:model="showCreateModal" class="md:w-[450px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Stok Opname Baru</flux:heading>
                <flux:subheading>Sistem akan menarik seluruh data stok saat ini sebagai referensi.</flux:subheading>
            </div>

            <flux:textarea wire:model="note" label="Catatan (Opsional)"
                placeholder="Contoh: Audit akhir bulan Februari..." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="startOpname">Mulai Opname</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Detail / Fill -->
    @if ($activeOpname)
        <flux:modal wire:model="showDetailModal" class="md:w-[800px]">
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

                <div class="max-h-[500px] overflow-y-auto border rounded-xl">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Produk</flux:table.column>
                            <flux:table.column>Stok Sistem</flux:table.column>
                            <flux:table.column>Stok Fisik</flux:table.column>
                            <flux:table.column>Selisih</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($activeOpname->items as $item)
                                <flux:table.row :key="$item->id">
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <span class="font-medium text-sm">{{ $item->product->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $item->product->sku }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $item->system_stock }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($activeOpname->status === 'DRAFT')
                                            <flux:input type="number"
                                                wire:model.live.debounce.500ms="physicalStocks.{{ $item->product_id }}" size="sm"
                                                class="w-24" />
                                        @else
                                            {{ $item->physical_stock }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell
                                        class="font-bold {{ $item->difference < 0 ? 'text-red-500' : ($item->difference > 0 ? 'text-green-500' : '') }}">
                                        {{ $item->difference > 0 ? '+' : '' }}{{ $item->difference }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="flex justify-between items-center">
                    <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                    @if ($activeOpname->status === 'DRAFT')
                        <flux:button variant="primary"
                            wire:confirm="Finalisasi stok opname akan mengubah seluruh stok sistem. Lanjutkan?"
                            wire:click="finalize">Finalisasi & Update Stok</flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @endif
</div>