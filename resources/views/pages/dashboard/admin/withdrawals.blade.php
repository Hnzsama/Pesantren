<?php

use App\Models\Bill;
use App\Models\Withdrawal;
use App\Models\WalletTransaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Validate;

new #[Lazy] class extends Component {
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

    public $schoolId;

    // Form properties
    #[Validate('required|numeric|min:10000')]
    public $nominal = '';

    #[Validate('required|string')]
    public $bankName = '';

    #[Validate('required|string')]
    public $accountNumber = '';

    public $sourceType = 'REVENUE'; // Default to REVENUE as we are combining

    #[Validate('required|string')]
    public $accountHolderName = '';

    public $notes = '';

    public $showRequestModal = false;

    public $selectedWithdrawal = null;

    public function mount()
    {
        $this->schoolId = auth()->user()->school_id;
    }

    #[Computed]
    public function availableBalance()
    {
        // Combined balance of all digital/gateway money
        $totalIncome = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');

        $totalTopUp = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');

        $totalWithdrawn = Withdrawal::where('school_id', $this->schoolId)
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');

        $totalPlatformFees = WalletTransaction::where('school_id', $this->schoolId)
            ->where('payment_method', 'cash')
            ->where('category', 'TOPUP')
            ->sum('admin_fee');

        return ($totalIncome + $totalTopUp) - ($totalWithdrawn + $totalPlatformFees);
    }

    #[Computed]
    public function withdrawableBalance()
    {
        return max(0, $this->availableBalance - 500000);
    }

    #[Computed]
    public function totalDigitalIncome()
    {
        $totalIncome = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');

        $totalTopUp = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');

        return $totalIncome + $totalTopUp;
    }

    #[Computed]
    public function totalWithdrawnCommitted()
    {
        return Withdrawal::where('school_id', $this->schoolId)
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');
    }

    #[Computed]
    public function pendingWithdrawals()
    {
        return Withdrawal::where('school_id', $this->schoolId)
            ->whereIn('status', ['REQUESTED'])
            ->sum('amount');
    }

    #[Computed]
    public function withdrawals()
    {
        return Withdrawal::where('school_id', $this->schoolId)
            ->with(['requestedBy', 'approvedBy'])
            ->orderBy('requested_at', 'desc')
            ->get();
    }

    public function openRequestModal()
    {
        $this->reset(['nominal', 'bankName', 'accountNumber', 'accountHolderName', 'notes']);
        $this->showRequestModal = true;
    }

    public function submitRequest()
    {
        $this->validate();

        if ($this->nominal > ($this->availableBalance - 500000)) {
            $availableToWithdraw = max(0, $this->availableBalance - 500000);
            $this->addError('nominal', 'Saldo harus menyisakan minimal Rp 500.000 di sistem. Maksimal yang bisa ditarik saat ini: Rp ' . number_format($availableToWithdraw, 0, ',', '.'));
            // Clear financial cache
            Cache::forget("school_{$this->schoolId}_gateway_balance");
            return;
        }

        Withdrawal::create([
            'school_id' => $this->schoolId,
            'requested_by' => auth()->id(),
            'amount' => $this->nominal,
            'source_type' => 'REVENUE', // Use REVENUE as generic source
            'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber,
            'account_holder_name' => $this->accountHolderName,
            'status' => 'REQUESTED',
            'requested_balance_snapshot' => $this->availableBalance,
            'notes' => $this->notes,
        ]);

        session()->flash('message', 'Pengajuan penarikan berhasil dibuat!');
        $this->reset(['nominal', 'bankName', 'accountNumber', 'accountHolderName', 'notes', 'showRequestModal']);
    }

    public function viewDetail($withdrawalId)
    {
        $this->selectedWithdrawal = Withdrawal::with(['approvedBy'])
            ->where('school_id', $this->schoolId)
            ->findOrFail($withdrawalId);
        $this->modal('withdrawal-detail')->show();
    }

    public function closeDetail()
    {
        $this->selectedWithdrawal = null;
        $this->modal('withdrawal-detail')->close();
    }
    public function getStatusColor($status)
    {
        return match ($status) {
            'REQUESTED' => 'warning',
            'APPROVED' => 'info',
            'PAID' => 'success',
            'REJECTED' => 'danger',
            default => 'neutral',
        };
    }

    public function getStatusLabel($status)
    {
        return match ($status) {
            'REQUESTED' => 'Menunggu Persetujuan',
            'APPROVED' => 'Disetujui',
            'PAID' => 'Dibayarkan',
            'REJECTED' => 'Ditolak',
            default => $status,
        };
    }
}; ?>

<div class="space-y-8">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Penarikan Dana</flux:heading>
            <flux:subheading size="lg" class="mb-6">Manajemen Penarikan Dana Pesantren ke Rekening</flux:subheading>
        </div>

        <flux:button wire:click="openRequestModal" variant="primary" icon="banknotes">
            Ajukan Penarikan Dana
        </flux:button>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        {{-- Total Saldo di Sistem (Total) --}}
        <flux:card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-zinc-50 dark:bg-zinc-900/10 border-zinc-200 dark:border-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <flux:text class="font-bold uppercase tracking-widest text-[10px]">Total Saldo di Sistem</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">
                Rp {{ number_format($this->availableBalance, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Saldo keseluruhan di Pusat</flux:subheading>
        </flux:card>

        {{-- Saldo Bisa Ditarik (Withdrawable) --}}
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30">
            <flux:text class="text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-widest text-[10px]">Saldo yang Bisa Ditarik</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums text-emerald-800 dark:text-emerald-300">
                Rp {{ number_format($this->withdrawableBalance, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-emerald-600/70 dark:text-emerald-400/60 italic">Sudah dikurangi saldo mengendap Rp 500rb
            </flux:subheading>
        </flux:card>

        {{-- Total Ditarik (Combined) --}}
        <flux:card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-zinc-50 dark:bg-zinc-900/10 border-zinc-200 dark:border-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <flux:text class="font-bold uppercase tracking-widest text-[10px]">Total Berhasil Ditarik</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">
                Rp {{ number_format($this->totalWithdrawnCommitted, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Dana yang telah dikirim ke rekening</flux:subheading>
        </flux:card>

        {{-- Pending --}}
        <flux:card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <flux:text class="text-amber-700 dark:text-amber-400 font-bold uppercase tracking-widest text-[10px]">Dalam Proses</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums text-amber-800 dark:text-amber-300">
                Rp {{ number_format($this->pendingWithdrawals, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-amber-600/70 dark:text-amber-400/60">Menunggu persetujuan HQ
            </flux:subheading>
        </flux:card>
    </div>

    {{-- Withdrawals Table --}}
    <flux:card class="space-y-6">
        <flux:heading size="lg">Riwayat Penarikan Dana Pusat</flux:heading>

        @if($this->withdrawals->isEmpty())
            <div class="py-12 text-center">
                <flux:subheading>Belum ada riwayat penarikan dana</flux:subheading>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-zinc-500">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-zinc-500">Nominal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-zinc-500">Rekening Tujuan</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-zinc-500">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-zinc-500 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->withdrawals as $withdrawal)
                            <tr class="group hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $withdrawal->requested_at->format('d M Y') }}</div>
                                    <div class="text-xs text-zinc-500">{{ $withdrawal->requested_at->format('H:i') }} WIB</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-base">
                                        Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-medium">{{ $withdrawal->bank_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $withdrawal->account_number }} a/n {{ $withdrawal->account_holder_name }}</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <flux:badge :color="$this->getStatusColor($withdrawal->status)" size="sm">
                                        {{ $this->getStatusLabel($withdrawal->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-4 text-sm text-right">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="viewDetail('{{ $withdrawal->id }}')">
                                        Detail
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    {{-- Request Modal --}}
    <flux:modal name="request-withdrawal" :showing="$showRequestModal" wire:model="showRequestModal" class="max-w-lg">
        <form wire:submit="submitRequest" class="space-y-6">
            <div>
                <flux:heading size="lg">Ajukan Penarikan Dana</flux:heading>
                <flux:subheading>Dana akan ditransfer ke rekening pesantren oleh pihak Pusat.</flux:subheading>
            </div>

            <div class="space-y-4">
                {{-- Available Balance Info --}}
                <div class="bg-zinc-50 dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl space-y-3 shadow-inner">
                    <div class="flex justify-between items-center text-sm">
                        <flux:text class="text-zinc-500 font-medium">Total Saldo Tersedia</flux:text>
                        <div class="font-bold text-zinc-900 dark:text-zinc-100">
                            Rp {{ number_format($this->availableBalance, 0, ',', '.') }}
                        </div>
                    </div>
                    
                    <flux:separator variant="subtle" />

                    <div class="flex justify-between items-center">
                        <div class="flex flex-col">
                            <flux:text class="text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-tight text-[10px]">Dana yang Bisa Ditarik</flux:text>
                            <flux:text class="text-[9px] text-zinc-500 italic">Telah dikurangi saldo minimal Rp 500.000</flux:text>
                        </div>
                        <div class="text-2xl font-black text-emerald-800 dark:text-emerald-300 tabular-nums">
                            Rp {{ number_format($this->withdrawableBalance, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <flux:field>
                    <flux:label>Nominal Penarikan</flux:label>
                    <flux:input wire:model="nominal" type="number" placeholder="0" icon="banknotes" />
                    <flux:error name="nominal" />
                    <flux:description>Min. penarikan Rp 10.000. Saldo harus menyisakan minimal Rp 500.000 di sistem.</flux:description>
                </flux:field>

                <div class="grid grid-cols-1 gap-4">
                    <flux:field>
                        <flux:label>Nama Bank</flux:label>
                        <flux:input wire:model="bankName" placeholder="Contoh: BCA, Mandiri" />
                        <flux:error name="bankName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Nomor Rekening</flux:label>
                        <flux:input wire:model="accountNumber" placeholder="1234567890" />
                        <flux:error name="accountNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Nama Pemilik Rekening</flux:label>
                        <flux:input wire:model="accountHolderName" placeholder="Nama sesuai buku tabungan" />
                        <flux:error name="accountHolderName" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Catatan (Opsional)</flux:label>
                    <flux:textarea wire:model="notes" placeholder="Berikan catatan jika diperlukan..." rows="2" />
                </flux:field>
            </div>

            <div class="flex gap-2 pt-4">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showRequestModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">Kirim Pengajuan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Detail Modal --}}
    <flux:modal name="withdrawal-detail" flyout class="space-y-6">
        @if($selectedWithdrawal)
            <div>
                <flux:heading size="lg">Detail Penarikan</flux:heading>
                <flux:subheading>Informasi lengkap pengajuan penarikan dana</flux:subheading>
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl">
                    <flux:text>Status</flux:text>
                    <flux:badge size="lg" :color="$this->getStatusColor($selectedWithdrawal->status)">
                        {{ $this->getStatusLabel($selectedWithdrawal->status) }}
                    </flux:badge>
                </div>

                <div class="space-y-2">
                    <flux:text class="text-xs font-bold uppercase tracking-wider text-zinc-500">Informasi Nominal</flux:text>
                    <div class="bg-zinc-50 dark:bg-zinc-900 p-4 rounded-2xl space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Jumlah Penarikan</span>
                            <span class="font-bold text-lg">Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Biaya Admin</span>
                            <span class="text-green-600 font-medium">Gratis</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <flux:text class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rekening Tujuan</flux:text>
                    <div class="bg-zinc-50 dark:bg-zinc-900 p-4 rounded-2xl space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Bank</span>
                            <span class="font-medium">{{ $selectedWithdrawal->bank_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">No. Rekening</span>
                            <span class="font-medium">{{ $selectedWithdrawal->account_number }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Atas Nama</span>
                            <span class="font-medium">{{ $selectedWithdrawal->account_holder_name }}</span>
                        </div>
                    </div>
                </div>

                @if($selectedWithdrawal->payment_proof && $selectedWithdrawal->status === 'PAID')
                    <div class="space-y-2">
                        <flux:text class="text-xs font-bold uppercase tracking-wider text-zinc-500">Bukti Transfer</flux:text>
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden shadow-sm">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($selectedWithdrawal->payment_proof) }}"
                                class="w-full h-auto cursor-pointer hover:scale-105 transition-transform duration-300" 
                                onclick="window.open(this.src, '_blank')" alt="Bukti Transfer">
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <flux:text class="text-xs font-bold uppercase tracking-wider text-zinc-500">Diajukan Pada</flux:text>
                        <div class="text-sm font-medium">{{ $selectedWithdrawal->requested_at->format('d M Y, H:i') }}</div>
                    </div>
                    @if($selectedWithdrawal->approved_at)
                        <div class="space-y-1">
                            <flux:text class="text-xs font-bold uppercase tracking-wider text-zinc-500">Diproses Pada</flux:text>
                            <div class="text-sm font-medium text-emerald-600">{{ $selectedWithdrawal->approved_at->format('d M Y, H:i') }}</div>
                        </div>
                    @endif
                </div>

                @if($selectedWithdrawal->notes)
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800 rounded-2xl">
                        <flux:text class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase mb-1">Catatan</flux:text>
                        <flux:text size="sm">{{ $selectedWithdrawal->notes }}</flux:text>
                    </div>
                @endif
            </div>

            <div class="flex pt-4">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="closeDetail">Tutup</flux:button>
            </div>
        @endif
    </flux:modal>
</div>