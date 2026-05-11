<?php

use App\Models\Student;
use App\Models\Bill;
use App\Models\WalletTransaction;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    public $student_id = '';
    public $scan_input = '';
    public $selected_bill_id = '';
    public $admin_fee = 0;
    public $last_invoice = null;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        $students = Student::where('school_id', $schoolId)
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->get();

        $unpaidBills = [];
        if ($this->student_id) {
            $unpaidBills = Bill::where('student_id', $this->student_id)
                ->where('status', 'UNPAID')
                ->orderBy('due_date', 'asc')
                ->get();
        }

        $totalUnpaidAmount = 0;
        if ($this->student_id) {
            $totalUnpaidAmount = collect($unpaidBills)->sum('amount');
        }

        return [
            'students' => $students,
            'unpaidBills' => $unpaidBills,
            'totalUnpaidAmount' => $totalUnpaidAmount,
        ];
    }

    public function processPayment()
    {
        $this->validate([
            'student_id' => 'required|exists:students,id',
            'selected_bill_id' => 'required|exists:bills,id',
            'admin_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () {
                $bill = Bill::findOrFail($this->selected_bill_id);
                $user = auth()->user();

                if ($bill->status === 'PAID') {
                    throw new \Exception('Tagihan ini sudah lunas.');
                }

                // Calculate platform fee (half of the admin fee paid by parent)
                $platformFee = (float)($this->admin_fee ?: 0) / 2;

                // 1. Update Bill status
                $bill->update([
                    'status' => 'PAID',
                    'paid_at' => now(),
                    'payment_method' => 'MANUAL', // Manual Cash
                ]);

                // 2. Create Wallet Transaction for record
                WalletTransaction::create([
                    'school_id' => $bill->school_id,
                    'invoice_code' => $bill->invoice_code,
                    'student_id' => $bill->student_id,
                    'pic_user_id' => $user->id,
                    'operator_id' => $user->id,
                    'type' => 'DEBIT',
                    'category' => 'PURCHASE',
                    'amount' => $bill->amount,
                    'admin_fee' => $platformFee,
                    'payment_method' => 'cash',
                    'status' => 'PAID',
                    'description' => 'Pembayaran Tagihan Manual: ' . $bill->title,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->last_invoice = $bill->invoice_code;
                
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pembayaran tagihan berhasil diproses.');
                $this->reset(['selected_bill_id', 'admin_fee']);
            });
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: $e->getMessage());
        }
    }

    public function updatedStudentId()
    {
        $this->selected_bill_id = '';
        $this->last_invoice = null;
        
        // If student has exactly one unpaid bill, auto-select it for even faster processing
        $bills = Bill::where('student_id', $this->student_id)
            ->where('status', 'UNPAID')
            ->get();
            
        if ($bills->count() > 0) {
            $this->selected_bill_id = $bills->first()->id;
        }
    }

    public function updatedScanInput()
    {
        if (empty($this->scan_input)) return;

        // Try to find by NIS first
        $student = Student::where('nis', $this->scan_input)
            ->where('school_id', auth()->user()->school_id)
            ->first();

        // If not found by NIS, try to find via StudentCard
        if (!$student) {
            $card = \App\Models\StudentCard::where('card_number', $this->scan_input)->first();
            if ($card && $card->student) {
                $student = $card->student;
            }
        }

        if ($student) {
            $this->student_id = $student->id;
            $this->updatedStudentId();
            Flux::toast(variant: 'success', heading: 'Santri Ditemukan', text: $student->full_name);
        } else {
            Flux::toast(variant: 'danger', heading: 'Tidak Ditemukan', text: 'NIS atau kartu tidak terdaftar.');
        }

        $this->scan_input = '';
    }
}; ?>

<div class="w-full py-2 px-2 sm:px-4">
    <div class="space-y-4">
        <!-- Header Section (Compact) -->
        <div class="flex items-center justify-between gap-4 bg-white/5 dark:bg-zinc-900/50 p-4 rounded-3xl backdrop-blur-sm border border-zinc-200 dark:border-white/10">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-blue-500/10 rounded-2xl">
                    <flux:icon name="banknotes" variant="outline" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="lg" class="font-black tracking-tight leading-none">Pembayaran Tagihan Manual</flux:heading>
                    <flux:text class="text-[10px] text-zinc-500 font-medium mt-1">Proses tunai via kantor/admin</flux:text>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('bills.index') }}" variant="subtle" size="sm" icon="list-bullet" class="text-[10px] font-bold uppercase tracking-wider">Riwayat</flux:button>
                <flux:button href="{{ route('dashboard') }}" variant="subtle" size="sm" icon="arrow-left" class="text-[10px] font-bold uppercase tracking-wider">Kembali</flux:button>
            </div>
        </div>


        <!-- Dynamic Content Area -->
        <div class="space-y-4">
            @if(!$student_id)
                <!-- Tutorial Steps (Compact) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-in fade-in duration-500">
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-500">
                            <flux:icon name="magnifying-glass" size="sm" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 1</div>
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Pilih/Cari Santri</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                            <flux:icon name="list-bullet" size="sm" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 2</div>
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Pilih Tagihan</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 rounded-[24px] p-4 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                            <flux:icon name="check-circle" size="sm" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Langkah 3</div>
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Konfirmasi Bayar</div>
                        </div>
                    </div>
                </div>
            @endif

            @if($student_id)
                @php
                    $selectedStudent = $students->firstWhere('id', $student_id);
                @endphp
                @if($selectedStudent)
                    <!-- Selected Student Summary (Refined Layout) -->
                    <div class="max-w-2xl animate-in slide-in-from-top-4 duration-500">
                        <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5 shadow-xl rounded-3xl overflow-hidden relative group">
                            <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                            <div class="flex items-center justify-between gap-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-50 dark:bg-blue-500/10 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                                        <flux:icon name="user" variant="mini" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <flux:text class="text-[9px] font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Santri Terpilih</flux:text>
                                        <flux:heading size="md" class="font-black text-zinc-900 dark:text-white leading-none">{{ $selectedStudent->full_name }}</flux:heading>
                                        <flux:text class="text-[11px] text-zinc-500 font-medium">{{ $selectedStudent->nis }} • {{ $selectedStudent->level->name ?? 'Tanpa Kelas' }}</flux:text>
                                    </div>
                                </div>
                                
                                <flux:button wire:click="$set('student_id', '')" variant="subtle" color="zinc" size="sm" class="rounded-xl font-bold uppercase tracking-wider text-[10px]">
                                    <flux:icon name="arrow-path" variant="mini" class="mr-2" />
                                    Ganti
                                </flux:button>
                            </div>
                        </flux:card>
                    </div>
                @endif
            @endif

            @if($last_invoice)
                <flux:card class="p-6 bg-emerald-50 dark:bg-emerald-900/10 border-emerald-100 dark:border-emerald-900/30 rounded-3xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm ring-1 ring-emerald-500/20 animate-in slide-in-from-top-2">
                    <div class="flex items-center gap-4">
                        <div class="p-4 bg-emerald-500 rounded-2xl text-white shadow-lg shadow-emerald-500/30">
                            <flux:icon name="check" variant="outline" class="w-8 h-8" />
                        </div>
                        <div>
                            <flux:heading size="lg" class="text-emerald-900 dark:text-emerald-400 font-bold">Pembayaran Berhasil!</flux:heading>
                            <flux:subheading class="text-emerald-800/80 dark:text-emerald-500/60 font-medium">Struk pembayaran telah tersedia.</flux:subheading>
                        </div>
                    </div>
                    <flux:button variant="primary" color="green" icon="printer" class="w-full md:w-auto shadow-lg shadow-emerald-500/20" href="{{ route('wallet-transactions.print', $last_invoice) }}" target="_blank">Cetak Struk Sekarang</flux:button>
                </flux:card>
            @endif

            <!-- Main Form Area (Truly Full Width) -->
            <flux:card class="p-6 bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-white/5 rounded-[28px] overflow-hidden relative">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-emerald-500"></div>
                
                <form wire:submit="processPayment" class="space-y-6 mt-2">
                    <div class="space-y-8">
                        @if(!$student_id)
                            <div class="flex flex-col md:flex-row md:items-end gap-6 max-w-3xl p-6 bg-zinc-50/50 dark:bg-zinc-900/50 rounded-2xl border border-zinc-100 dark:border-white/5">
                                <div class="flex-1">
                                    <flux:select wire:model.live="student_id" label="Pilih Santri (Manual)" variant="listbox" searchable placeholder="Cari nama atau NIS santri..." class="h-12">
                                        @foreach ($students as $student)
                                            <flux:select.option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>
                        @endif

                        @if($student_id)
                            @if(count($unpaidBills) > 0)
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <!-- Column 1: Bill Selection -->
                                    <div class="space-y-6">
                                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-blue-50/50 dark:bg-blue-900/10 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                                            <div class="flex items-center gap-4">
                                                <div class="p-3 bg-blue-500 rounded-xl text-white">
                                                    <flux:icon name="banknotes" class="w-6 h-6" />
                                                </div>
                                                <div>
                                                    <flux:heading size="sm" class="text-blue-900 dark:text-blue-300 font-bold uppercase tracking-wider">Total Semua Tagihan</flux:heading>
                                                    <flux:subheading class="text-blue-700/70 dark:text-blue-400/60 italic text-xs">Total yang belum dilunasi santri ini</flux:subheading>
                                                </div>
                                            </div>
                                            <div class="text-3xl font-black text-blue-600 dark:text-blue-400 tabular-nums">
                                                Rp {{ number_format($totalUnpaidAmount, 0, ',', '.') }}
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                            <flux:label class="text-lg font-bold text-zinc-800 dark:text-zinc-100 uppercase tracking-tight">Pilih Tagihan untuk Dibayar</flux:label>
                                            <flux:badge color="orange" size="sm" inset="top bottom">{{ count($unpaidBills) }} Tersedia</flux:badge>
                                        </div>
                                        
                                        <flux:radio.group wire:model.live="selected_bill_id" variant="cards" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            @foreach($unpaidBills as $bill)
                                                <flux:radio value="{{ $bill->id }}" class="group transition-all duration-300">
                                                    <div class="flex flex-col h-full justify-between gap-4 py-2">
                                                        <div class="space-y-1">
                                                            <div class="font-black text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-tight">{{ $bill->title }}</div>
                                                            <div class="text-[10px] text-zinc-400 font-mono">{{ $bill->invoice_code }}</div>
                                                        </div>
                                                        
                                                        <div class="flex items-end justify-between border-t border-zinc-100 dark:border-zinc-800 pt-3 gap-2">
                                                            <div class="text-[10px] text-zinc-500 flex items-center gap-1 shrink-0">
                                                                <flux:icon name="calendar" class="w-3 h-3" />
                                                                {{ $bill->due_date->format('d/m/Y') }}
                                                            </div>
                                                            <div class="text-lg font-black text-blue-600 dark:text-blue-400 tabular-nums">
                                                                Rp {{ number_format($bill->amount, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </flux:radio>
                                            @endforeach
                                        </flux:radio.group>
                                    </div>

                                    <!-- Column 2: Payment Details & Confirmation -->
                                    <div class="space-y-6">
                                        @if($selected_bill_id)
                                            <div class="bg-zinc-50/50 dark:bg-zinc-900/50 p-8 rounded-[32px] border border-zinc-100 dark:border-white/5 shadow-inner space-y-6 sticky top-4">
                                                <div class="space-y-4">
                                                    <flux:heading size="lg" class="font-black text-zinc-900 dark:text-white uppercase tracking-tight">Rincian Pembayaran Tunai</flux:heading>
                                                    
                                                    @php
                                                        $selectedBill = collect($unpaidBills)->firstWhere('id', $selected_bill_id);
                                                        $billAmount = $selectedBill ? $selectedBill->amount : 0;
                                                        $totalPay = (float)$billAmount + (float)($admin_fee ?: 0);
                                                    @endphp

                                                    <div class="space-y-4">
                                                        <flux:input 
                                                            value="Rp {{ number_format($billAmount, 0, ',', '.') }}" 
                                                            label="Nominal Tagihan" 
                                                            icon="banknotes" 
                                                            readonly 
                                                            class="h-11 bg-zinc-100 dark:bg-zinc-800/50 border-zinc-200 dark:border-white/5 opacity-80 cursor-not-allowed" 
                                                        />
                                                        
                                                        <flux:input wire:model.live="admin_fee" type="number" label="Biaya Admin" icon="receipt-percent" prefix="Rp" placeholder="0" class="h-11 border-zinc-200 dark:border-white/5" />
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

                                                <div class="flex flex-col gap-1">
                                                    <span class="font-black text-zinc-900 dark:text-zinc-100 text-2xl tracking-tight uppercase leading-none">Total Terima Tunai</span>
                                                    <span class="text-xs text-zinc-500 mb-4">Jumlah fisik uang dari wali murid</span>
                                                    
                                                    <div class="px-8 py-4 bg-blue-600 rounded-2xl shadow-xl shadow-blue-500/30 text-center">
                                                        <span class="font-black text-4xl text-white tabular-nums">
                                                            Rp {{ number_format($totalPay, 0, ',', '.') }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="pt-4">
                                                    <flux:button type="submit" variant="primary" icon="check-circle" class="w-full h-16 rounded-2xl text-xl font-black shadow-2xl shadow-blue-500/40">
                                                        Konfirmasi & Bayar
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="h-full flex flex-col items-center justify-center p-12 bg-zinc-50/30 dark:bg-zinc-900/30 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-[32px] text-center">
                                                <div class="p-4 bg-white dark:bg-zinc-800 rounded-full shadow-sm mb-4">
                                                    <flux:icon name="hand-raised" class="w-12 h-12 text-zinc-300" />
                                                </div>
                                                <flux:heading size="md" class="text-zinc-400 font-bold uppercase tracking-widest">Pilih Tagihan</flux:heading>
                                                <flux:text class="text-zinc-400">Silakan pilih salah satu tagihan di sebelah kiri untuk melanjutkan pembayaran.</flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="p-10 bg-zinc-50 dark:bg-zinc-900/50 rounded-[40px] border border-zinc-100 dark:border-zinc-800 text-center relative overflow-hidden">
                                    <div class="absolute top-0 right-0 p-8 opacity-5">
                                        <flux:icon name="check-badge" class="w-32 h-32" />
                                    </div>
                                    <div class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner">
                                        <flux:icon name="check" variant="outline" class="w-8 h-8 text-emerald-500" />
                                    </div>
                                    <flux:heading size="lg" class="text-zinc-900 dark:text-white font-black">Luar Biasa!</flux:heading>
                                    <flux:subheading size="lg" class="text-zinc-500 dark:text-zinc-400">Santri ini sudah melunasi semua tagihannya.</flux:subheading>
                                </div>
                            @endif
                        @endif
                </form>
            </flux:card>
        </div>
    </div>
</div>
