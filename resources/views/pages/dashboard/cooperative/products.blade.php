<?php

use App\Models\Product;
use App\Models\ProductMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

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

    public $editingId = null;
    public $showModal = false;
    public $showStockModal = false;

    // Form fields
    public $name = '';
    public $sku = '';
    public $type = 'PRODUCT';
    public $price = 0;
    public $stock = 0;
    public $is_active = true;

    // Stock add fields
    public $selectedProduct = null;
    public $addStockQty = 0;
    public $stockNote = '';

    public function with(): array
    {
        $query = Product::query()
            ->where('school_id', auth()->user()->school_id)
            ->latest();

        if ($this->search) {
            $query->where(
                fn($q) =>
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%')
            );
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        return [
            'products' => $query->paginate(15),
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $this->editingId = $id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->type = $product->type;
        $this->price = $product->price;
        $this->stock = $product->stock;
        $this->is_active = $product->is_active;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'type' => 'required|in:PRODUCT,SERVICE',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
        ]);

        $data = [
            'school_id' => auth()->user()->school_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type,
            'price' => $this->price,
            'stock' => $this->type === 'PRODUCT' ? $this->stock : null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Product::find($this->editingId)->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Produk berhasil diperbarui.');
        } else {
            $product = Product::create($data);

            // Log initial stock if any
            if ($product->type === 'PRODUCT' && $product->stock > 0) {
                ProductMovement::create([
                    'school_id' => $product->school_id,
                    'product_id' => $product->id,
                    'type' => 'IN',
                    'quantity' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                    'source' => 'MANUAL',
                    'note' => 'Stok awal',
                    'pic_user_id' => auth()->id(),
                ]);
            }
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Produk berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function openAddStock($id)
    {
        $this->selectedProduct = Product::findOrFail($id);
        $this->addStockQty = 0;
        $this->stockNote = '';
        $this->showStockModal = true;
    }

    public function addStock()
    {
        $this->validate([
            'addStockQty' => 'required|integer|min:1',
            'stockNote' => 'nullable|string|max:255',
        ]);

        $product = $this->selectedProduct;
        $oldStock = $product->stock;
        $newStock = $oldStock + $this->addStockQty;

        $product->update(['stock' => $newStock]);

        ProductMovement::create([
            'school_id' => $product->school_id,
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => $this->addStockQty,
            'stock_before' => $oldStock,
            'stock_after' => $newStock,
            'source' => 'MANUAL',
            'note' => $this->stockNote ?: 'Tambah stok manual',
            'pic_user_id' => auth()->id(),
        ]);

        $this->showStockModal = false;
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Stok berhasil ditambahkan.');
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->sku = '';
        $this->type = 'PRODUCT';
        $this->price = 0;
        $this->stock = 0;
        $this->is_active = true;
    }
}; ?>

<div class="w-full">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Produk & Jasa') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Kelola barang dan jasa koperasi') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Baru') }}</flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:input wire:model.live="search" label="Cari Produk" placeholder="Nama atau SKU..." icon="magnifying-glass"
            clearable />
        <flux:select wire:model.live="typeFilter" label="Tipe">
            <flux:select.option value="">Semua Tipe</flux:select.option>
            <flux:select.option value="PRODUCT">Barang (PRODUCT)</flux:select.option>
            <flux:select.option value="SERVICE">Jasa (SERVICE)</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$products">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Harga</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell class="font-medium">{{ $product->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $product->sku ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :variant="$product->type === 'PRODUCT' ? 'neutral' : 'outline'">
                            {{ $product->type }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>Rp {{ number_format($product->price, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($product->type === 'PRODUCT')
                            <span class="{{ $product->stock <= 5 ? 'text-red-500 font-bold' : '' }}">
                                {{ $product->stock }}
                            </span>
                        @else
                            <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :variant="$product->is_active ? 'success' : 'danger'">
                            {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit('{{ $product->id }}')">Edit
                                </flux:menu.item>
                                @if ($product->type === 'PRODUCT')
                                    <flux:menu.item icon="plus-circle" wire:click="openAddStock('{{ $product->id }}')">Tambah
                                        Stok</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal Form -->
    <flux:modal wire:model="showModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Produk' : 'Tambah Produk' }}</flux:heading>
                <flux:subheading>Lengkapi informasi produk atau jasa di bawah ini.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Nama Produk/Jasa" placeholder="Contoh: Air Mineral..." />
                <flux:input wire:model="sku" label="SKU (Opsional)" placeholder="P-001" />

                <flux:select wire:model="type" label="Tipe">
                    <flux:select.option value="PRODUCT">Barang (PRODUCT)</flux:select.option>
                    <flux:select.option value="SERVICE">Jasa (SERVICE)</flux:select.option>
                </flux:select>

                <flux:input type="number" wire:model="price" label="Harga Jual" prefix="Rp" />

                @if ($type === 'PRODUCT' && !$editingId)
                    <flux:input type="number" wire:model="stock" label="Stok Awal" />
                @endif

                <flux:checkbox wire:model="is_active" label="Aktif" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="save">Simpan</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Add Stock -->
    @if ($selectedProduct)
        <flux:modal wire:model="showStockModal" class="md:w-[400px]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tambah Stok</flux:heading>
                    <flux:subheading>{{ $selectedProduct->name }}</flux:subheading>
                </div>

                <div class="space-y-4">
                    <flux:text>Stok saat ini: <span class="font-bold">{{ $selectedProduct->stock }}</span></flux:text>
                    <flux:input type="number" wire:model="addStockQty" label="Jumlah Masuk" />
                    <flux:textarea wire:model="stockNote" label="Catatan"
                        placeholder="Contoh: Barang datang dari supplier..." />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showStockModal', false)">Batal</flux:button>
                    <flux:button variant="primary" wire:click="addStock">Tambah Stok</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>