<?php

use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Product;
use App\Models\WalletTransaction;
use App\Services\TransactionService;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Sensitive;
use Livewire\Component;
use Flux\Flux;

new #[Lazy] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full animate-pulse p-6">
            <div class="flex justify-between items-center mb-8">
                <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
                <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-lg"></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-8 h-[600px] bg-zinc-50 dark:bg-zinc-950 rounded-3xl"></div>
                <div class="lg:col-span-4 h-[600px] bg-zinc-50 dark:bg-zinc-950 rounded-3xl"></div>
            </div>
        </div>
        HTML;
    }

    #[Validate('required')]
    public $cardNumber = '';

    public $amount = 0; 

    #[Sensitive]
    #[Validate('required|string|min:4|max:6')]
    public $pin = '';

    public $student = null;
    
    // POS Cart
    public $cart = [];
    public $productSearch = '';
    public $activeTypeFilter = ''; 

    public function with(): array
    {
        $productsQuery = Product::where('school_id', auth()->user()->school_id)
            ->where('is_active', true);

        if ($this->productSearch) {
            $productsQuery->where(fn($q) => 
                $q->where('name', 'like', '%' . $this->productSearch . '%')
                  ->orWhere('sku', 'like', '%' . $this->productSearch . '%')
            );
        }

        if ($this->activeTypeFilter) {
            $productsQuery->where('type', $this->activeTypeFilter);
        }

        return [
            'products' => $productsQuery->orderBy('name')->paginate(12),
        ];
    }

    public function updatedCardNumber($value)
    {
        if (empty($value)) {
            $this->student = null;
            return;
        }

        $card = StudentCard::where('card_number', $value)
            ->whereHas('student', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->with('student')
            ->first();

        $this->student = $card ? $card->student : null;

        if ($this->student) {
            Flux::toast(variant: 'success', heading: 'Santri Teridentifikasi', text: $this->student->full_name);
            $this->dispatch('student-locked');
        } else {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: 'Kartu tidak terdaftar.');
            $this->cardNumber = '';
        }
    }

    public function addToCart($productId)
    {
        $product = Product::findOrFail($productId);
        
        if ($product->type === 'PRODUCT' && $product->stock <= 0) {
            Flux::toast(text: 'Stok barang ini sudah habis!', variant: 'danger');
            return;
        }

        $exists = false;
        foreach ($this->cart as &$item) {
            if ($item['id'] === $productId) {
                if ($product->type === 'PRODUCT' && $item['quantity'] + 1 > $product->stock) {
                    Flux::toast(text: 'Maaf, stok tidak mencukupi.', variant: 'warning');
                    return;
                }
                $item['quantity']++;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $this->cart[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float)$product->price,
                'quantity' => 1,
                'type' => $product->type,
            ];
        }

        $this->calculateTotal();
        $this->dispatch('item-added');
    }

    public function updateQuantity($index, $change)
    {
        $item = &$this->cart[$index];
        $product = Product::find($item['id']);

        $newQty = $item['quantity'] + $change;

        if ($newQty < 1) {
            $this->removeFromCart($index);
            return;
        }

        if ($product->type === 'PRODUCT' && $newQty > $product->stock) {
            Flux::toast(text: 'Stok terbatas: hanya sisa ' . $product->stock, variant: 'warning');
            return;
        }

        $item['quantity'] = $newQty;
        $this->calculateTotal();
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $this->amount = array_reduce($this->cart, function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);
    }

    public function processPurchase(TransactionService $service)
    {
        if (!$this->student) {
            Flux::toast('Mohon scan kartu santri terlebih dahulu.', variant: 'danger');
            return;
        }

        $this->validate();

        if (empty($this->cart)) {
            Flux::toast('Belum ada item yang dibeli.', variant: 'danger');
            return;
        }

        try {
            $items = array_map(fn($item) => [
                'product_id' => $item['id'],
                'quantity' => $item['quantity']
            ], $this->cart);

            $service->processPurchase($this->student, (float)$this->amount, $this->pin, auth()->user(), $items);

            Flux::toast('Transaksi Berhasil! ✨', variant: 'success');

            $this->reset(['cardNumber', 'amount', 'pin', 'student', 'cart', 'productSearch']);
            $this->dispatch('transaction-reset');
        } catch (\Exception $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
            $this->pin = '';
        }
    }
}; ?>

<div class="w-full flex flex-col h-[calc(100vh-120px)] overflow-hidden" 
    x-data="{
        showPin: false,
        focusCard() { this.$nextTick(() => { this.$refs.cardInput?.querySelector('input')?.focus(); }); },
        focusPin() { setTimeout(() => { this.$refs.pinInput?.querySelector('input')?.focus(); }, 150); }
    }" 
    x-init="focusCard()" 
    @transaction-reset.window="focusCard(); showPin = false"
    @student-locked.window="if(amount > 0) showPin = true; focusPin();"
    @item-added.window="if(student) { showPin = true; focusPin(); }">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-accent rounded-2xl shadow-lg shadow-accent/20">
                <flux:icon.shopping-cart class="w-6 h-6 text-accent-foreground" />
            </div>
            <div>
                <h1 class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white uppercase line-clamp-1">POS KOPERASI</h1>
                <p class="text-sm text-zinc-500 font-medium">Point of Sale & Inventory System</p>
            </div>
        </div>

        <div class="flex items-center gap-2 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl border dark:border-zinc-700">
            <button @click="$wire.set('activeTypeFilter', '')" 
                class="px-4 py-2 text-xs font-bold rounded-lg transition-all {{ $activeTypeFilter === '' ? 'bg-white dark:bg-zinc-700 shadow-sm text-accent' : 'text-zinc-500 hover:text-zinc-800' }}">
                SEMUA
            </button>
            <button @click="$wire.set('activeTypeFilter', 'PRODUCT')" 
                class="px-4 py-2 text-xs font-bold rounded-lg transition-all {{ $activeTypeFilter === 'PRODUCT' ? 'bg-white dark:bg-zinc-700 shadow-sm text-accent' : 'text-zinc-500 hover:text-zinc-800' }}">
                BARANG
            </button>
            <button @click="$wire.set('activeTypeFilter', 'SERVICE')" 
                class="px-4 py-2 text-xs font-bold rounded-lg transition-all {{ $activeTypeFilter === 'SERVICE' ? 'bg-white dark:bg-zinc-700 shadow-sm text-accent' : 'text-zinc-500 hover:text-zinc-800' }}">
                JASA
            </button>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 flex-1 min-h-0">
        
        <!-- Left: Catalog (8 Columns) -->
        <div class="lg:col-span-8 flex flex-col min-h-0 space-y-4">
            <!-- Search Area -->
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <flux:icon.magnifying-glass class="w-5 h-5 text-zinc-400 group-focus-within:text-accent transition-colors" />
                </div>
                <input wire:model.live.debounce.300ms="productSearch" 
                    type="text"
                    placeholder="Cari produk atau scan barcode..." 
                    class="w-full pl-11 pr-4 py-4 bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 rounded-2xl focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-medium shadow-sm">
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto pr-3 custom-scrollbar -mr-3 pb-8">
                @if($products->isEmpty())
                    <div class="flex flex-col items-center justify-center h-64 bg-zinc-50/50 dark:bg-zinc-900/30 rounded-3xl border-2 border-dashed border-zinc-100 dark:border-zinc-800">
                        <div class="p-6 bg-white dark:bg-zinc-800 rounded-full shadow-sm mb-4">
                            <flux:icon.archive-box-x-mark class="w-12 h-12 text-zinc-300" />
                        </div>
                        <h4 class="font-bold text-zinc-400 uppercase tracking-widest text-xs">Produk Tidak Ditemukan</h4>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach($products as $p)
                            <button wire:click="addToCart('{{ $p->id }}')" 
                                class="group relative flex flex-col bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-[2rem] overflow-hidden hover:border-accent hover:shadow-xl hover:shadow-accent/5 transition-all duration-300 active:scale-[0.98]">
                                
                                <div class="aspect-square bg-zinc-50 dark:bg-zinc-950/50 relative overflow-hidden">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        @if($p->type === 'PRODUCT')
                                            <flux:icon.shopping-bag class="w-14 h-14 text-zinc-200 dark:text-zinc-800 group-hover:text-accent/20 transition-colors" />
                                        @else
                                            <flux:icon.wrench-screwdriver class="w-14 h-14 text-zinc-200 dark:text-zinc-800 group-hover:text-accent/20 transition-colors" />
                                        @endif
                                    </div>
                                    
                                    <!-- Stock Badge -->
                                    @if($p->type === 'PRODUCT')
                                        <div class="absolute top-4 right-4">
                                            <div class="px-2 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter {{ $p->stock <= 5 ? 'bg-red-100 text-red-600' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500' }}">
                                                {{ $p->stock }} UNIT
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Price Badge -->
                                    <div class="absolute bottom-4 left-4">
                                        <div class="bg-accent text-accent-foreground px-3 py-1 rounded-full text-xs font-black shadow-lg shadow-accent/30">
                                            Rp {{ number_format($p->price, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <h4 class="font-black text-sm text-zinc-800 dark:text-zinc-200 line-clamp-2 leading-tight uppercase group-hover:text-accent transition-colors">{{ $p->name }}</h4>
                                        <p class="text-[10px] text-zinc-400 font-mono mt-1 opacity-60">{{ $p->sku ?? 'NO-SKU' }}</p>
                                    </div>
                                </div>

                                <div class="absolute inset-0 bg-gradient-to-t from-accent/[0.03] to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            </div>
        </div>

        <!-- Right: Cart & Invoice (4 Columns) -->
        <div class="lg:col-span-4 flex flex-col min-h-0 relative">
            
            <div class="absolute -top-12 -right-4 w-64 h-64 bg-accent/5 rounded-full blur-3xl -z-10"></div>
            <div class="absolute -bottom-12 -left-4 w-48 h-48 bg-accent/5 rounded-full blur-3xl -z-10"></div>

            <flux:card class="flex-1 flex flex-col min-h-0 !p-0 overflow-hidden border-none shadow-2xl shadow-zinc-200/50 dark:shadow-none bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl rounded-[2.5rem]">
                <!-- Cart Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-zinc-50 to-white dark:from-zinc-900 dark:to-zinc-900 border-b dark:border-zinc-800 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <h3 class="font-black text-xs tracking-widest text-zinc-400 uppercase">PESANAN BARU</h3>
                    </div>
                    @if(!empty($cart))
                        <button wire:click="$set('cart', []); calculateTotal()" class="text-[10px] font-black text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 px-2 py-1 rounded-md transition-all">HAPUS</button>
                    @endif
                </div>

                <!-- Receipt Items -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                    @forelse($cart as $index => $item)
                        <div class="flex items-start gap-3 p-4 bg-white dark:bg-zinc-950 rounded-3xl border border-zinc-100 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all group">
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs font-black text-zinc-800 dark:text-zinc-200 uppercase leading-tight">{{ $item['name'] }}</h5>
                                <p class="text-[10px] text-zinc-500 font-mono mt-1 opacity-70">
                                    {{ $item['quantity'] }} x Rp {{ number_format($item['price'], 0, ',', '.') }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-xs font-black text-zinc-900 dark:text-white">
                                    Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                </span>
                                <div class="flex items-center bg-zinc-50 dark:bg-zinc-900 rounded-full p-1 border dark:border-zinc-800 group-hover:scale-110 transition-transform">
                                    <button wire:click="updateQuantity({{ $index }}, -1)" class="w-5 h-5 flex items-center justify-center hover:bg-white dark:hover:bg-zinc-800 rounded-full transition-colors">
                                        <flux:icon.minus class="w-2 h-2" />
                                    </button>
                                    <span class="w-6 text-center text-[10px] font-black">{{ $item['quantity'] }}</span>
                                    <button wire:click="updateQuantity({{ $index }}, 1)" class="w-5 h-5 flex items-center justify-center hover:bg-white dark:hover:bg-zinc-800 rounded-full transition-colors">
                                        <flux:icon.plus class="w-2 h-2" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-full text-center space-y-4 opacity-30">
                            <div class="p-8 bg-zinc-100 dark:bg-zinc-800 rounded-full">
                                <flux:icon.shopping-cart class="w-12 h-12" />
                            </div>
                            <p class="text-sm font-black uppercase tracking-widest">KERANJANG KOSONG</p>
                        </div>
                    @endforelse
                </div>

                <!-- Footer / Checkout Area -->
                <div class="p-6 bg-zinc-50 dark:bg-zinc-900/50 border-t dark:border-zinc-800">
                    
                    <!-- Grand Total -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">TOTAL TAGIHAN</span>
                            <div class="flex items-center gap-2">
                                <span class="text-accent font-black text-3xl">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-accent/10 flex items-center justify-center">
                            <flux:icon.banknotes class="w-6 h-6 text-accent" />
                        </div>
                    </div>

                    <!-- Action Steps -->
                    <div class="space-y-4">
                        @if(!$student)
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <flux:icon.credit-card class="w-5 h-5 text-zinc-400 group-focus-within:text-accent" />
                                </div>
                                <input wire:model.live.debounce.500ms="cardNumber" 
                                    type="number" 
                                    placeholder="SCAN KARTU SANTRI..." 
                                    x-ref="cardInput"
                                    class="w-full pl-11 py-5 bg-white dark:bg-zinc-800 border-2 border-accent/20 font-black text-center tracking-[0.2em] rounded-3xl focus:ring-4 focus:ring-accent/10 focus:border-accent transition-all">
                                <div class="absolute -top-2 left-6 px-3 bg-white dark:bg-zinc-800 text-[10px] font-black text-accent border border-accent/20 rounded-full">IDENTIFIKASI PEMBELI</div>
                            </div>
                        @else
                            <!-- Profile Section -->
                            <div class="relative group p-4 bg-gradient-to-br from-accent to-accent-content rounded-[2rem] text-accent-foreground shadow-xl shadow-accent/20">
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0 w-14 h-14 bg-white/20 rounded-2xl backdrop-blur-md flex items-center justify-center border border-white/30">
                                        <flux:icon.user class="w-8 h-8 text-white" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-black text-sm uppercase truncate">{{ $student->full_name }}</h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="px-2 py-0.5 bg-white/20 rounded text-[10px] font-black tracking-widest border border-white/20">SALDO</div>
                                            <span class="text-xs font-black">Rp {{ number_format($student->wallet_balance, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                    <button wire:click="$set('student', null)" class="w-8 h-8 rounded-full bg-black/10 hover:bg-black/20 flex items-center justify-center transition-colors">
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Payment Confirm Button -->
                            @if(!empty($cart))
                                <div class="pt-2">
                                    <button x-show="!showPin" 
                                        @click="showPin = true; focusPin()"
                                        class="w-full py-5 bg-accent hover:opacity-90 text-accent-foreground font-black text-sm uppercase tracking-widest rounded-3xl shadow-xl shadow-accent/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                                        KONFIRMASI PEMBAYARAN
                                        <flux:icon.arrow-right class="w-4 h-4" />
                                    </button>
                                    
                                    <!-- PIN Entry Overlay-ish -->
                                    <div x-show="showPin" x-cloak x-transition class="space-y-4">
                                        <div class="p-6 bg-white dark:bg-zinc-800 rounded-[2.5rem] border-2 border-accent/20 shadow-inner">
                                            <label class="block text-center text-[10px] font-black text-accent uppercase tracking-[0.2em] mb-4">MASUKKAN PIN KEAMANAN</label>
                                            <div class="flex justify-center" x-ref="pinInput">
                                                <flux:otp wire:model.live.debounce.150ms="pin" length="6" />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <button @click="showPin = false" class="py-4 font-black text-xs text-zinc-400 uppercase tracking-widest hover:text-zinc-600 transition-colors">BATAL</button>
                                            <button wire:click="processPurchase" 
                                                class="py-4 bg-accent text-accent-foreground rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg disabled:opacity-50"
                                                :disabled="strlen($pin) < 4">
                                                BAYAR SEKARANG
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { 
            background: #e2e8f0; 
            border-radius: 10px; 
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
        [x-cloak] { display: none !important; }
        
        @keyframes subtle-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .animate-subtle-pulse { animation: subtle-pulse 2s infinite ease-in-out; }
    </style>
</div>