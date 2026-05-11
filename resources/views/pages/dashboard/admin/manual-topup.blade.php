<?php

use App\Models\Student;
use App\Models\WalletTransaction;
use App\Services\TransactionService;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Lazy] #[Layout('layouts.app')] #[Title('Top Up Manual')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-8 w-full animate-pulse">
            <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            <div class="h-96 bg-zinc-50 dark:bg-zinc-900 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
        </div>
        HTML;
    }

    public $student_id = '';
    public $amount = '';
    public $admin_fee = 2000;
    public $description = '';
    public $payment_method = 'cash';
    public $last_invoice = null;

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        
        $students = Student::where('school_id', $schoolId)
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->get();

        return [
            'students' => $students,
        ];
    }

    public function submit(TransactionService $transactionService)
    {
        $this->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:10000',
            'admin_fee' => 'required|numeric|min:0',
        ], [
            'student_id.required' => 'Pilih santri terlebih dahulu',
            'amount.required' => 'Masukkan nominal top up',
            'amount.min' => 'Minimal top up Rp 10.000',
        ]);

        try {
            $student = Student::findOrFail($this->student_id);
            
            $platformFee = (float) $this->admin_fee / 2;

            $transaction = $transactionService->processTopup(
                $student, 
                (float) $this->amount, 
                auth()->user(), 
                $platformFee,
                $this->description,
                $this->payment_method
            );

            $this->last_invoice = $transaction->invoice_code;

            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Top up manual untuk ' . $student->full_name . ' berhasil diproses.');
            
            $this->reset(['student_id', 'amount', 'description']);
            $this->admin_fee = 2000;

        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }
}; ?>

<div class="w-full py-2 px-2 sm:px-4">
    <div class="space-y-4">
        <!-- Header Section (Compact) -->
        <div class="flex items-center justify-between gap-4 bg-white/5 dark:bg-zinc-900/50 p-4 rounded-3xl backdrop-blur-sm border border-zinc-200 dark:border-white/10">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-blue-500/10 rounded-2xl">
                    <flux:icon name="plus-circle" variant="outline" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="lg" class="font-black tracking-tight leading-none">Top Up Manual (Tunai)</flux:heading>
                    <flux:text class="text-[10px] text-zinc-500 font-medium mt-1">Terima setoran tunai dari orang tua santri</flux:text>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('dashboard') }}" variant="subtle" size="sm" icon="arrow-left" class="text-[10px] font-bold uppercase tracking-wider">Kembali</flux:button>
            </div>
        </div>

        <!-- Tutorial Steps (Compact) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-in fade-in duration-500">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-500">
                    <flux:icon name="user" size="sm" />
                </div>
                <div class="space-y-0.5">
                    <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 1</div>
                    <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Pilih Santri</div>
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                    <flux:icon name="banknotes" size="sm" />
                </div>
                <div class="space-y-0.5">
                    <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 2</div>
                    <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Terima Tunai</div>
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                    <flux:icon name="check-circle" size="sm" />
                </div>
                <div class="space-y-0.5">
                    <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 3</div>
                    <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Proses & Struk</div>
                </div>
            </div>
        </div>

        @if ($last_invoice)
            <flux:card class="p-6 bg-emerald-50 dark:bg-emerald-900/10 border-emerald-100 dark:border-emerald-900/30 rounded-3xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm ring-1 ring-emerald-500/20">
                <div class="flex items-center gap-4">
                    <div class="p-4 bg-emerald-500 rounded-2xl text-white shadow-lg shadow-emerald-500/30">
                        <flux:icon name="check" variant="outline" class="w-8 h-8" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-emerald-900 dark:text-emerald-400 font-bold">Top Up Berhasil!</flux:heading>
                        <flux:subheading class="text-emerald-800/80 dark:text-emerald-500/60 font-medium italic">Invoice: {{ $last_invoice }}</flux:subheading>
                    </div>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <flux:button variant="primary" color="green" icon="printer" class="flex-1 md:flex-none shadow-lg shadow-emerald-500/20" href="{{ route('wallet-transactions.print', $last_invoice) }}" target="_blank">Cetak Struk Bukti</flux:button>
                    <flux:button wire:click="$set('last_invoice', null)" icon="x-mark" variant="ghost">Tutup</flux:button>
                </div>
            </flux:card>
        @endif

        <!-- Form Area -->
        <flux:card class="p-6 bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-white/5 rounded-[28px] overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-emerald-500"></div>
            
            <form wire:submit="submit" class="space-y-6 mt-2">
                <div class="space-y-6">
                    <div class="max-w-xl">
                        <flux:select wire:model="student_id" searchable label="Pilih Santri" variant="listbox" filter placeholder="Cari nama atau NIS santri..." class="h-11 text-sm border-zinc-200 dark:border-white/5">
                            @foreach ($students as $student)
                                <flux:select.option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <flux:input wire:model.live="amount" type="number" label="Nominal Top Up" icon="banknotes" prefix="Rp" placeholder="0" class="h-11 text-base border-zinc-200 dark:border-white/5" />
                                <flux:input wire:model.live="admin_fee" type="number" label="Biaya Admin" icon="receipt-percent" prefix="Rp" placeholder="0" class="h-11 text-base border-zinc-200 dark:border-white/5" />
                            </div>

                            <flux:textarea wire:model="description" label="Keterangan (Opsional)" placeholder="Contoh: Titipan tunai di kantor" rows="2" class="text-sm border-zinc-200 dark:border-white/5" />
                        </div>

                        <div class="bg-zinc-50/30 dark:bg-zinc-900/30 p-6 rounded-[24px] border border-zinc-200 dark:border-white/5 shadow-inner flex flex-col justify-center gap-4">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                                <div class="flex flex-col text-center md:text-left">
                                    <span class="font-black text-zinc-900 dark:text-zinc-100 text-xl tracking-tight uppercase leading-none">Total Terima Tunai</span>
                                    <span class="text-[10px] text-zinc-500 mt-1">Jumlah fisik uang dari wali murid</span>
                                </div>
                                <div class="px-6 py-3 bg-blue-600 rounded-2xl shadow-xl shadow-blue-500/20">
                                    <span class="font-black text-2xl text-white tabular-nums">
                                        Rp {{ number_format((float)($amount ?: 0) + (float)($admin_fee ?: 0), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                            
                            <flux:separator variant="subtle" />

                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 bg-white/50 dark:bg-zinc-800/50 rounded-xl border border-zinc-100 dark:border-white/5">
                                    <div class="text-[9px] text-zinc-500 uppercase font-bold text-center md:text-left">Profit Sekolah</div>
                                    <div class="font-bold text-emerald-600">Rp {{ number_format((float)($admin_fee ?: 0) / 2, 0, ',', '.') }}</div>
                                </div>
                                <div class="p-3 bg-white/50 dark:bg-zinc-800/50 rounded-xl border border-zinc-100 dark:border-white/5">
                                    <div class="text-[9px] text-zinc-500 uppercase font-bold text-center md:text-left">Platform Fee</div>
                                    <div class="font-bold text-blue-600">Rp {{ number_format((float)($admin_fee ?: 0) / 2, 0, ',', '.') }}</div>
                                </div>
                                <div class="col-span-full">
                                    <p class="text-[8px] text-zinc-400 italic text-center">* Platform fee akan dipotong dari saldo digital pesantren.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary" icon="check-circle" class="w-full md:w-auto h-14 px-10 rounded-2xl text-lg font-black shadow-xl shadow-blue-500/30">
                        Proses Top Up
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
