<?php

use App\Models\Product;
use App\Models\ProductMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Services\ImageCompressionService;
use Illuminate\Support\Str;
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

    use WithPagination, WithFileUploads;

    #[Url]
    public $search = '';

    #[Url]
    public $typeFilter = '';

    public $editingId = null;
    public $productIdToDelete = null;

    // Form fields
    public $name = '';
    public $sku = '';
    public $type = 'PRODUCT';
    public $selling_price = 0;
    public $purchase_price = 0;
    public $stock = 0;
    public $is_active = true;
    public $tempImage = null; // Stored file from cropper
    public $existingImage = null; // Existing image path

    public function updatedTempImage()
    {
        $this->validate([
            'tempImage' => 'image|max:10240',
        ]);
    }

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
        Flux::modal('product-form-modal')->show();
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $this->editingId = $id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->type = $product->type;
        $this->selling_price = $product->selling_price;
        $this->purchase_price = $product->purchase_price;
        $this->stock = $product->stock;
        $this->is_active = $product->is_active;
        $this->existingImage = $product->image;
        $this->tempImage = null;

        Flux::modal('product-form-modal')->show();
    }

    public function save(ImageCompressionService $compressor)
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'type' => 'required|in:PRODUCT,SERVICE',
            'selling_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
        ]);

        $data = [
            'school_id' => auth()->user()->school_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type,
            'selling_price' => $this->selling_price,
            'purchase_price' => $this->purchase_price,
            'stock' => $this->type === 'PRODUCT' ? $this->stock : null,
            'is_active' => $this->is_active,
        ];

        // Process uploaded image if exists
        if ($this->tempImage) {
            $filename = Str::uuid();
            $data['image'] = $compressor->storeAsWebP($this->tempImage, 'products', $filename, 80);
        } else if (!$this->existingImage) {
            $data['image'] = null;
        }

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

        Flux::modal('product-form-modal')->close();
        $this->resetForm();
    }

    public function confirmDelete($id)
    {
        $this->productIdToDelete = $id;
        $this->modal('delete-product-modal')->show();
    }

    public function destroy()
    {
        $product = Product::find($this->productIdToDelete);
        if ($product) {
            $product->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Produk berhasil dihapus.');
        }
        $this->reset('productIdToDelete');
        $this->modal('delete-product-modal')->close();
    }

    public function openAddStock($id)
    {
        $this->selectedProduct = Product::findOrFail($id);
        $this->addStockQty = 0;
        $this->stockNote = '';
        Flux::modal('add-stock-modal')->show();
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

        Flux::modal('add-stock-modal')->close();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Stok berhasil ditambahkan.');
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->sku = '';
        $this->type = 'PRODUCT';
        $this->selling_price = 0;
        $this->purchase_price = 0;
        $this->stock = 0;
        $this->is_active = true;
        $this->tempImage = null;
        $this->existingImage = null;
    }
}; ?>

<div class="w-full">
    <!-- Include Cropper.js CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Produk & Jasa') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Kelola barang dan jasa koperasi') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create" class="w-full md:w-auto">{{ __('Tambah Baru') }}</flux:button>
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
            <flux:table.column>Harga Jual</flux:table.column>
            <flux:table.column>Harga Beli</flux:table.column>
            <flux:table.column>Margin</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden shrink-0 flex items-center justify-center">
                                @if($product->image)
                                    <img src="{{ Storage::url($product->image) }}" class="w-full h-full object-cover">
                                @else
                                    <flux:icon.photo class="w-5 h-5 text-zinc-400" />
                                @endif
                            </div>
                            <span class="font-medium">{{ $product->name }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $product->sku ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :variant="$product->type === 'PRODUCT' ? 'neutral' : 'outline'">
                            {{ $product->type }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>Rp {{ number_format($product->selling_price, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>Rp {{ number_format($product->purchase_price, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>
                        @php
                            $marginRp = $product->selling_price - $product->purchase_price;
                            $marginPct = $product->purchase_price > 0 ? ($marginRp / $product->purchase_price) * 100 : ($product->selling_price > 0 ? 100 : 0);
                        @endphp
                        <div class="flex flex-col">
                            <span class="{{ $marginRp > 0 ? 'text-green-600 dark:text-green-400' : ($marginRp < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500') }} font-medium text-sm">
                                {{ $marginRp > 0 ? '+' : '' }}Rp {{ number_format($marginRp, 0, ',', '.') }}
                            </span>
                            <span class="text-xs text-zinc-500">{{ number_format($marginPct, 1, ',', '.') }}%</span>
                        </div>
                    </flux:table.cell>
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
                                <flux:menu.separator />
                                <flux:modal.trigger name="delete-product-modal">
                                    <flux:menu.item variant="danger" icon="trash" wire:click="confirmDelete('{{ $product->id }}')">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal Form -->
    <flux:modal name="product-form-modal" class="w-full max-w-lg">
        <div class="space-y-6" x-data="{
                    showCropper: false,
                    cropper: null,
                    croppedImage: null,
                    
                    fileSelected(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            document.getElementById('crop-image').src = e.target.result;
                            this.showCropper = true;
                            
                            setTimeout(() => {
                                if (this.cropper) this.cropper.destroy();
                                this.cropper = new Cropper(document.getElementById('crop-image'), {
                                    aspectRatio: 1, viewMode: 1, autoCropArea: 1, background: false
                                });
                            }, 100);
                        };
                        reader.readAsDataURL(file);
                    },
                    
                    applyCrop() {
                        if (!this.cropper) return;
                        const canvas = this.cropper.getCroppedCanvas({ width: 500, height: 500 });
                        this.croppedImage = canvas.toDataURL('image/webp', 0.8);
                        
                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            let file = new File([blob], 'product_' + Date.now() + '.webp', { type: 'image/webp' });
                            this.$wire.upload('tempImage', file, () => {
                                this.$wire.set('existingImage', null);
                            });
                        }, 'image/webp', 0.8);
                        
                        this.cropper.destroy();
                        this.cropper = null;
                        this.showCropper = false;
                        if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                    },
                    
                    cancelCrop() {
                        if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                        this.showCropper = false;
                        if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                    },
                    
                    removeImage() {
                        this.croppedImage = null;
                        this.$wire.set('tempImage', null);
                        this.$wire.set('existingImage', null);
                    }
                }" x-effect="if(!$modal('product-form-modal').open) { croppedImage = null; showCropper = false; }">
            
            <!-- FORM VIEW -->
            <div x-show="!showCropper" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editingId ? 'Edit Produk' : 'Tambah Produk' }}</flux:heading>
                    <flux:subheading>Lengkapi informasi produk atau jasa di bawah ini.</flux:subheading>
                </div>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <flux:label>Gambar Produk (1:1)</flux:label>
                        <input type="file" x-ref="fileInput" @change="fileSelected" accept="image/*" class="hidden">
                        
                        <div class="flex items-center gap-4">
                            <div class="relative w-20 h-20 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 overflow-hidden bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center shrink-0">
                                <template x-if="croppedImage">
                                    <img :src="croppedImage" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!croppedImage && $wire.existingImage">
                                    <img :src="'/storage/' + $wire.existingImage" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!croppedImage && !$wire.existingImage">
                                    <flux:icon.photo class="w-8 h-8 text-zinc-400" />
                                </template>
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <flux:button size="sm" @click="$refs.fileInput.click()">Pilih Gambar</flux:button>
                                <template x-if="croppedImage || $wire.existingImage">
                                    <flux:button size="sm" variant="danger" x-show="croppedImage || $wire.tempImage || $wire.existingImage" @click="removeImage">Hapus</flux:button>
                                </template>
                            </div>
                        </div>
                    </div>

                <flux:input wire:model="name" label="Nama Produk/Jasa" placeholder="Contoh: Air Mineral..." />
                <flux:input wire:model="sku" label="SKU (Opsional)" placeholder="P-001" />

                <flux:select wire:model="type" label="Tipe">
                    <flux:select.option value="PRODUCT">Barang (PRODUCT)</flux:select.option>
                    <flux:select.option value="SERVICE">Jasa (SERVICE)</flux:select.option>
                </flux:select>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input type="number" wire:model="purchase_price" label="Harga Beli" prefix="Rp" />
                    <flux:input type="number" wire:model="selling_price" label="Harga Jual" prefix="Rp" />
                </div>

                @if ($type === 'PRODUCT' && !$editingId)
                    <flux:input type="number" wire:model="stock" label="Stok Awal" />
                @endif

                <flux:checkbox wire:model="is_active" label="Aktif" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">Simpan</flux:button>
            </div>
        </div>

        <!-- CROPPER VIEW -->
        <div x-show="showCropper" style="display: none;" class="space-y-6">
            <div class="flex justify-between items-center mb-4">
                <flux:heading>Potong Gambar</flux:heading>
                <flux:button variant="ghost" size="sm" icon="x-mark" @click="cancelCrop" />
            </div>
            <div class="flex-1 overflow-hidden min-h-[300px] bg-zinc-100 rounded-lg">
                <img id="crop-image" class="max-w-full block">
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <flux:button variant="ghost" @click="cancelCrop">Batal</flux:button>
                <flux:button variant="primary" @click="applyCrop">Potong & Simpan</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Add Stock -->
    @if ($selectedProduct)
        <flux:modal name="add-stock-modal" class="w-full max-w-md">
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
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="addStock">Tambah Stok</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Delete Modal -->
    <flux:modal name="delete-product-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Produk?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus produk ini?<br>
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