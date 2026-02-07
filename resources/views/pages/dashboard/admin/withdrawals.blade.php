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

    #[Validate('required|string|in:REVENUE,TRUST_FUND')]
    public $sourceType = 'REVENUE';

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
    public function availableBalanceRevenue()
    {
        // WALLET A: Saldo Utama Pesantren (Revenue)
        $totalIncome = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');

        $totalWithdrawnRevenue = Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'REVENUE')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');

        return $totalIncome - $totalWithdrawnRevenue;
    }

    #[Computed]
    public function availableBalanceTrust()
    {
        // WALLET B: Saldo Dana Titipan (Trust Fund Cash Pool in Gateway)
        $totalTopUp = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->sum('amount');

        $totalWithdrawnTrust = Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'TRUST_FUND')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');

        return $totalTopUp - $totalWithdrawnTrust;
    }

    #[Computed]
    public function availableBalance()
    {
        return $this->sourceType === 'REVENUE'
            ? $this->availableBalanceRevenue
            : $this->availableBalanceTrust;
    }

    #[Computed]
    public function totalTopUp()
    {
        // WALLET B: Total Dana Titipan (Gross historical)
        return WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->sum('amount');
    }

    #[Computed]
    public function totalWithdrawnRevenue()
    {
        // Total APPROVED/PAID from Revenue source
        return Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'REVENUE')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');
    }

    #[Computed]
    public function totalWithdrawnTrust()
    {
        // Total APPROVED/PAID from Trust Fund source
        return Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'TRUST_FUND')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');
    }

    #[Computed]
    public function totalWithdrawnCommitted()
    {
        // Combined total withdrawal commitment
        return $this->totalWithdrawnRevenue + $this->totalWithdrawnTrust;
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

    // NO FEE CALCULATION - Withdrawals are fee-free
    // Pesantren receives full amount requested

    public function submitRequest()
    {
        $this->validate();

        // Check if amount exceeds available balance
        if ($this->nominal > $this->availableBalance) {
            $this->addError('nominal', 'Jumlah penarikan melebihi saldo tersedia.');
            return;
        }

        // Create withdrawal request - NO FEES
        // Pesantren receives FULL amount
        Withdrawal::create([
            'school_id' => $this->schoolId,
            'requested_by' => auth()->id(),
            'amount' => $this->nominal,
            'source_type' => $this->sourceType,
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
            <flux:subheading size="lg" class="mb-6">Manajemen Penarikan Dana Pesantren</flux:subheading>
        </div>

        <flux:button wire:click="openRequestModal" variant="primary" icon="banknotes">
            Ajukan Penarikan Dana
        </flux:button>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Dana Titipan (Gross) --}}
        <flux:card class="overflow-hidden bg-purple-50 dark:bg-purple-900/10 border-purple-200 dark:border-purple-800">
            <flux:text class="text-purple-700 dark:text-purple-400 font-medium">Total Dana Titipan Santri</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums text-purple-800 dark:text-purple-300">
                Rp {{ number_format($this->totalTopUp, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-purple-600/70 dark:text-purple-400/60">Akumulasi seluruh top-up santri
            </flux:subheading>
        </flux:card>

        {{-- Total Ditarik (Combined) --}}
        <flux:card class="overflow-hidden bg-zinc-50 dark:bg-zinc-900/10 border-zinc-200 dark:border-zinc-800">
            <flux:text class="font-medium">Total Ditarik ke HQ</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">
                Rp {{ number_format($this->totalWithdrawnCommitted, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Dana yang telah diterima</flux:subheading>
        </flux:card>

        {{-- Saldo Pendapatan (Available A) --}}
        <flux:card
            class="overflow-hidden bg-emerald-50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800">
            <flux:text class="text-emerald-700 dark:text-emerald-400 font-medium">Saldo Tersedia (Revenue)</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums text-emerald-800 dark:text-emerald-300">
                Rp {{ number_format($this->availableBalanceRevenue, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-emerald-600/70 dark:text-emerald-400/60">Pendapatan yang bisa ditarik
            </flux:subheading>
        </flux:card>

        {{-- Saldo Trust (Available B) --}}
        <flux:card class="overflow-hidden bg-purple-50 dark:bg-purple-900/10 border-purple-200 dark:border-purple-800">
            <flux:text class="text-purple-700 dark:text-purple-400 font-medium">Saldo Tersedia (Trust)</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums text-purple-800 dark:text-purple-300">
                Rp {{ number_format($this->availableBalanceTrust, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-purple-600/70 dark:text-purple-400/60">Dana titipan yang bisa ditarik
            </flux:subheading>
        </flux:card>
    </div>

    {{-- Withdrawal Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card variant="subtle" class="flex items-center gap-4">
            <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600">
                <flux:icon.banknotes class="w-6 h-6" />
            </div>
            <div>
                <flux:text size="sm">Revenue Ditarik</flux:text>
                <flux:heading size="lg" class="tabular-nums">Rp
                    {{ number_format($this->totalWithdrawnRevenue, 0, ',', '.') }}</flux:heading>
            </div>
        </flux:card>

        <flux:card variant="subtle" class="flex items-center gap-4">
            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg text-purple-600">
                <flux:icon.wallet class="w-6 h-6" />
            </div>
            <div>
                <flux:text size="sm">Dana Titipan Ditarik</flux:text>
                <flux:heading size="lg" class="tabular-nums">Rp
                    {{ number_format($this->totalWithdrawnTrust, 0, ',', '.') }}</flux:heading>
            </div>
        </flux:card>

        <flux:card variant="subtle" class="flex items-center gap-4">
            <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg text-orange-600">
                <flux:icon.clock class="w-6 h-6" />
            </div>
            <div>
                <flux:text size="sm">Menunggu Persetujuan</flux:text>
                <flux:heading size="lg" class="tabular-nums text-orange-600">Rp
                    {{ number_format($this->pendingWithdrawals, 0, ',', '.') }}</flux:heading>
            </div>
        </flux:card>
    </div>

    {{-- Withdrawals Table --}}
    <flux:card class="space-y-6">
        <flux:heading size="lg">Riwayat Penarikan</flux:heading>

        @if($this->withdrawals->isEmpty())
            <div class="py-12 text-center">
                <flux:subheading>Belum ada riwayat penarikan dana</flux:subheading>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Sumber</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Nominal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Bank</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->withdrawals as $withdrawal)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-4 py-3 text-sm">
                                    {{ $withdrawal->requested_at->format('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <flux:badge size="sm" :color="$withdrawal->source_type === 'REVENUE' ? 'emerald' : 'purple'"
                                        icon="wallet">
                                        {{ $withdrawal->source_type === 'REVENUE' ? 'Revenue' : 'Trust Fund' }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-semibold">
                                        Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div>{{ $withdrawal->bank_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $withdrawal->account_number }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <flux:badge :color="$this->getStatusColor($withdrawal->status)">
                                        {{ $this->getStatusLabel($withdrawal->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <flux:button size="sm" variant="ghost" wire:click="viewDetail('{{ $withdrawal->id }}')">
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
    <flux:modal name="request-withdrawal" :showing="$showRequestModal" wire:model="showRequestModal">
        <form wire:submit="submitRequest">
            <flux:heading size="lg" class="mb-4">Ajukan Penarikan Dana</flux:heading>

            <div class="space-y-4">
                {{-- Source Selection --}}
                <flux:field>
                    <flux:label>Pilih Sumber Dana</flux:label>
                    <flux:radio.group wire:model.live="sourceType" variant="cards" class="flex flex-col gap-2">
                        <flux:radio value="REVENUE" icon="banknotes" label="Saldo Utama Pesantren (Revenue)"
                            :description="'Rp ' . number_format($this->availableBalanceRevenue, 0, ',', '.')" />
                        <flux:radio value="TRUST_FUND" icon="wallet" label="Saldo Dana Titipan Santri (Trust)"
                            :description="'Rp ' . number_format($this->availableBalanceTrust, 0, ',', '.')" />
                    </flux:radio.group>
                    <flux:error name="sourceType" />
                </flux:field>

                {{-- Selected Balance Info --}}
                <flux:card
                    class="{{ $sourceType === 'REVENUE' ? 'bg-emerald-50 dark:bg-emerald-900/10 border-emerald-200' : 'bg-purple-50 dark:bg-purple-900/10 border-purple-200' }}">
                    <div class="flex justify-between items-center">
                        <flux:subheading>Saldo Tersedia ({{ $sourceType === 'REVENUE' ? 'Revenue' : 'Trust Fund' }})
                        </flux:subheading>
                        <div
                            class="text-xl font-bold {{ $sourceType === 'REVENUE' ? 'text-emerald-700' : 'text-purple-700' }}">
                            Rp {{ number_format($this->availableBalance, 0, ',', '.') }}
                        </div>
                    </div>
                </flux:card>

                <flux:field>
                    <flux:label>Nominal Penarikan</flux:label>
                    <flux:input wire:model="nominal" type="number" placeholder="Masukkan nominal" />
                    <flux:error name="nominal" />
                    <flux:description>Minimum penarikan: Rp 10.000. Dana akan diterima PENUH tanpa potongan fee.
                    </flux:description>
                </flux:field>

                {{-- Bank Details --}}
                <flux:field>
                    <flux:label>Nama Bank</flux:label>
                    <flux:input wire:model="bankName" placeholder="Contoh: BCA, Mandiri, BNI" />
                    <flux:error name="bankName" />
                </flux:field>

                <flux:field>
                    <flux:label>Nomor Rekening</flux:label>
                    <flux:input wire:model="accountNumber" placeholder="Masukkan nomor rekening" />
                    <flux:error name="accountNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>Nama Pemilik Rekening</flux:label>
                    <flux:input wire:model="accountHolderName" placeholder="Masukkan nama pemilik rekening" />
                    <flux:error name="accountHolderName" />
                </flux:field>

                {{-- Notes --}}
                <flux:field>
                    <flux:label>Catatan (Opsional)</flux:label>
                    <flux:textarea wire:model="notes" placeholder="Catatan tambahan..." rows="3" />
                </flux:field>
            </div>

            {{-- Actions --}}
            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showRequestModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Ajukan Penarikan
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Detail Modal --}}
    <flux:modal name="withdrawal-detail" flyout class="space-y-6">
        @if($selectedWithdrawal)
            <flux:heading size="lg" class="mb-4">Detail Penarikan</flux:heading>

            <div class="space-y-4">
                {{-- Status Badge --}}
                <div class="flex items-center gap-3">
                    <flux:badge size="lg" :color="$this->getStatusColor($selectedWithdrawal->status)">
                        {{ $this->getStatusLabel($selectedWithdrawal->status) }}
                    </flux:badge>
                    @if($selectedWithdrawal->payment_proof)
                        <div class="flex items-center gap-1 text-sm text-green-600">
                            <flux:icon.photo class="w-4 h-4" />
                            <span>Bukti tersedia</span>
                        </div>
                    @endif
                </div>

                {{-- Nominal --}}
                <div>
                    <flux:subheading class="text-xs text-zinc-500 mb-2">Nominal Penarikan</flux:subheading>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-lg">
                            <flux:text>Sumber Dana</flux:text>
                            <flux:badge :color="$selectedWithdrawal->source_type === 'REVENUE' ? 'emerald' : 'purple'"
                                icon="wallet">
                                {{ $selectedWithdrawal->source_type === 'REVENUE' ? 'Revenue' : 'Trust Fund' }}
                            </flux:badge>
                        </div>

                        <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-lg">
                            <flux:text>Nominal</flux:text>
                            <div class="text-xl font-bold">
                                Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-sm text-zinc-500 mt-1">
                            Dana diterima PENUH tanpa potongan fee
                        </div>
                    </div>
                </div>

                {{-- Bank Info --}}
                <div>
                    <flux:subheading class="text-xs text-zinc-500 mb-2">Rekening Tujuan</flux:subheading>
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded space-y-1">
                        <div><strong>Bank:</strong> {{ $selectedWithdrawal->bank_name }}</div>
                        <div><strong>No. Rekening:</strong> {{ $selectedWithdrawal->account_number }}</div>
                        <div><strong>Pemilik:</strong> {{ $selectedWithdrawal->account_holder_name }}</div>
                    </div>
                </div>

                {{-- Payment Proof (Only for PAID) --}}
                @if($selectedWithdrawal->payment_proof && $selectedWithdrawal->status === 'PAID')
                    <div>
                        <flux:subheading class="text-xs text-zinc-500 mb-2">Bukti Transfer</flux:subheading>
                        <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded">
                            <img src="{{ asset('storage/' . $selectedWithdrawal->payment_proof) }}"
                                class="w-full h-auto rounded border cursor-pointer hover:opacity-80 transition object-contain"
                                style="max-height: 400px;" alt="Bukti Transfer" onclick="window.open(this.src, '_blank')">
                            <div class="text-xs text-zinc-500 mt-2 flex items-center gap-1">
                                <flux:icon.eye class="w-4 h-4" />
                                Klik gambar untuk memperbesar
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Timeline Info --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <flux:subheading class="text-xs text-zinc-500">Diminta Pada</flux:subheading>
                        <div>{{ $selectedWithdrawal->requested_at->format('d M Y H:i') }}</div>
                    </div>
                    @if($selectedWithdrawal->approvedBy)
                        <div>
                            <flux:subheading class="text-xs text-zinc-500">Diproses Oleh</flux:subheading>
                            <div>{{ $selectedWithdrawal->approvedBy->name }}</div>
                            @if($selectedWithdrawal->approved_at)
                                <div class="text-xs text-zinc-500">{{ $selectedWithdrawal->approved_at->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Notes --}}
                @if($selectedWithdrawal->notes)
                    <flux:card class="bg-blue-50 dark:bg-blue-900/20">
                        <flux:subheading class="text-xs text-blue-600 mb-1">Catatan</flux:subheading>
                        <div class="text-sm">{{ $selectedWithdrawal->notes }}</div>
                    </flux:card>
                @endif

                {{-- Rejection Reason --}}
                @if($selectedWithdrawal->status === 'REJECTED' && $selectedWithdrawal->rejection_reason)
                    <flux:card class="bg-red-50 dark:bg-red-900/20">
                        <flux:subheading class="text-xs text-red-600 mb-1">Alasan Penolakan</flux:subheading>
                        <div class="text-sm">{{ $selectedWithdrawal->rejection_reason }}</div>
                    </flux:card>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <flux:modal.close>
                    <flux:button>Tutup</flux:button>
                </flux:modal.close>
            </div>
        @endif
    </flux:modal>
</div>