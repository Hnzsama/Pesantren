<?php

use App\Models\School;
use App\Models\Withdrawal;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

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

    use WithPagination, WithFileUploads;

    public $selectedSchool = '';

    public $selectedStatus = '';

    public $selectedWithdrawal = null;

    public $rejectionReason = '';

    public $paymentNotes = '';

    public $paymentProof; // File upload for payment proof

    #[Computed]
    public function schools()
    {
        return School::orderBy('name')->get();
    }

    #[Computed]
    public function withdrawals()
    {
        $query = Withdrawal::with(['school', 'requestedBy', 'approvedBy'])
            ->orderBy('requested_at', 'desc');

        // Filter by school
        if ($this->selectedSchool) {
            $query->where('school_id', $this->selectedSchool);
        }

        // Filter by status
        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function totalPending()
    {
        return Withdrawal::whereIn('status', ['REQUESTED', 'APPROVED'])
            ->sum('amount');
    }

    #[Computed]
    public function totalPaidToday()
    {
        return Withdrawal::where('status', 'PAID')
            ->whereDate('paid_at', today())
            ->sum('amount');
    }

    #[Computed]
    public function totalPaidThisMonth()
    {
        return Withdrawal::where('status', 'PAID')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');
    }

    public function viewDetail($withdrawalId)
    {
        $this->selectedWithdrawal = Withdrawal::with(['school', 'requestedBy', 'approvedBy'])
            ->findOrFail($withdrawalId);
        $this->modal('withdrawal-detail')->show();
    }

    public function openApproveModal($withdrawalId)
    {
        $this->selectedWithdrawal = Withdrawal::with('school')->findOrFail($withdrawalId);

        // Check if already approved or paid
        if (in_array($this->selectedWithdrawal->status, ['APPROVED', 'PAID'])) {
            session()->flash('error', 'Penarikan ini sudah disetujui atau dibayarkan.');
            return;
        }

        $this->modal('withdrawal-approve')->show();
    }

    public function approveWithdrawal()
    {
        if (!$this->selectedWithdrawal) {
            return;
        }

        $this->selectedWithdrawal->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->reset('selectedWithdrawal');
        $this->modal('withdrawal-approve')->close();
        session()->flash('message', 'Penarikan berhasil disetujui!');
    }

    public function openRejectModal($withdrawalId)
    {
        $this->selectedWithdrawal = Withdrawal::findOrFail($withdrawalId);

        // Check if already rejected or paid
        if (in_array($this->selectedWithdrawal->status, ['REJECTED', 'PAID'])) {
            session()->flash('error', 'Penarikan ini sudah ditolak atau dibayarkan.');

            return;
        }

        $this->reset('rejectionReason');
        $this->modal('withdrawal-reject')->show();
    }

    public function rejectWithdrawal()
    {
        if (!$this->selectedWithdrawal || !$this->rejectionReason) {
            $this->addError('rejectionReason', 'Alasan penolakan wajib diisi.');

            return;
        }

        $this->selectedWithdrawal->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejection_reason' => $this->rejectionReason,
            'approved_by' => auth()->id(), // Track who rejected
        ]);

        $this->reset(['selectedWithdrawal', 'rejectionReason']);
        $this->modal('withdrawal-reject')->close();
        session()->flash('message', 'Penarikan berhasil ditolak.');
    }

    public function openPayModal($withdrawalId)
    {
        $this->selectedWithdrawal = Withdrawal::with('school')->findOrFail($withdrawalId);

        // Check if not approved yet
        if ($this->selectedWithdrawal->status !== 'APPROVED') {
            session()->flash('error', 'Penarikan harus disetujui terlebih dahulu.');
            return;
        }

        // Check if already paid
        if ($this->selectedWithdrawal->status === 'PAID') {
            session()->flash('error', 'Penarikan ini sudah dibayarkan.');
            return;
        }

        // Check if locked and NOT expired (timeout 2 minutes)
        if ($this->selectedWithdrawal->locked_at && $this->selectedWithdrawal->locked_at->gt(now()->subMinutes(2))) {
            session()->flash('error', 'Penarikan sedang diproses oleh user lain atau sesi sebelumnya belum ditutup.');
            return;
        }

        // Lock the withdrawal
        $this->selectedWithdrawal->update(['locked_at' => now()]);

        // Reset form COMPLETELY
        $this->reset(['paymentNotes', 'paymentProof']);
        $this->resetValidation(); // Clear validation errors

        $this->modal('withdrawal-pay')->show();
    }

    public function markAsPaid()
    {
        if (!$this->selectedWithdrawal) {
            return;
        }

        // Validate payment proof
        $this->validate([
            'paymentProof' => 'required|image|max:2048', // Max 2MB
        ], [
            'paymentProof.required' => 'Bukti transfer wajib diupload',
            'paymentProof.image' => 'File harus berupa gambar',
            'paymentProof.max' => 'Ukuran file maksimal 2MB',
        ]);

        // Backend validation: Check available balance
        $availableBalance = $this->calculateSchoolBalance($this->selectedWithdrawal->school_id);

        if ($this->selectedWithdrawal->amount > $availableBalance) {
            session()->flash('error', 'Saldo sekolah tidak mencukupi untuk pembayaran ini!');
            $this->selectedWithdrawal->update(['locked_at' => null]); // Unlock
            $this->reset(['selectedWithdrawal', 'paymentNotes', 'paymentProof']);
            $this->resetValidation();
            $this->modal('withdrawal-pay')->close();
            return;
        }

        // Store payment proof
        $proofPath = $this->paymentProof->store('payment-proofs', 'public');

        // Update withdrawal status
        $this->selectedWithdrawal->update([
            'status' => 'PAID',
            'paid_at' => now(),
            'approved_by' => auth()->id(), // Track who processed payment
            'notes' => $this->paymentNotes,
            'payment_proof' => $proofPath,
            'locked_at' => null, // Unlock after successful payment
        ]);

        $this->reset(['selectedWithdrawal', 'paymentNotes', 'paymentProof']);
        $this->resetValidation();
        $this->modal('withdrawal-pay')->close();
        session()->flash('message', 'Pembayaran berhasil diproses!');
    }

    private function calculateSchoolBalance($schoolId)
    {
        $totalIncome = \App\Models\Bill::where('school_id', $schoolId)
            ->where('status', 'PAID')
            ->sum('amount');

        $totalWithdrawn = Withdrawal::where('school_id', $schoolId)
            ->where('status', 'PAID')
            ->sum('amount');

        return $totalIncome - $totalWithdrawn;
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

    public function cancelPayment()
    {
        if ($this->selectedWithdrawal) {
            $this->selectedWithdrawal->update(['locked_at' => null]);
        }

        // Reset ALL form data and validation
        $this->reset(['selectedWithdrawal', 'paymentNotes', 'paymentProof']);
        $this->resetValidation();
        $this->modal('withdrawal-pay')->close();
    }
}; ?>

<div class="space-y-8">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Kelola Penarikan Dana</flux:heading>
            <flux:subheading size="lg" class="mb-6">Manajemen Penarikan Pesantren</flux:subheading>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <flux:card class="bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200">
            <div class="flex items-center gap-3 text-emerald-700 dark:text-emerald-400">
                <flux:icon.check-circle class="w-5 h-5" />
                <span>{{ session('message') }}</span>
            </div>
        </flux:card>
    @endif

    @if (session()->has('error'))
        <flux:card class="bg-red-50 dark:bg-red-900/20 border-red-200">
            <div class="flex items-center gap-3 text-red-700 dark:text-red-400">
                <flux:icon.exclamation-triangle class="w-5 h-5" />
                <span>{{ session('error') }}</span>
            </div>
        </flux:card>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Pending Approval</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp {{ number_format($this->totalPending, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Total penarikan yang menunggu</flux:subheading>
        </flux:card>

        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Dibayarkan Hari Ini</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp {{ number_format($this->totalPaidToday, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Total pembayaran hari ini</flux:subheading>
        </flux:card>

        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Bulan Ini</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp
                {{ number_format($this->totalPaidThisMonth, 0, ',', '.') }}
            </flux:heading>
            <flux:subheading class="mt-1">Total penarikan bulan ini</flux:subheading>
        </flux:card>
    </div>

    {{-- Withdrawals Table --}}
    <flux:card class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <flux:heading size="lg">Daftar Penarikan</flux:heading>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <flux:select wire:model.live="selectedSchool" variant="listbox" searchable
                    placeholder="Filter Pesantren" class="w-full sm:min-w-[200px]">
                    <flux:select.option value="">Semua Sekolah</flux:select.option>
                    @foreach($this->schools as $school)
                        <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="selectedStatus" variant="listbox" placeholder="Filter Status"
                    class="w-full sm:w-48">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="REQUESTED">Menunggu Persetujuan</flux:select.option>
                    <flux:select.option value="APPROVED">Disetujui</flux:select.option>
                    <flux:select.option value="PAID">Dibayarkan</flux:select.option>
                    <flux:select.option value="REJECTED">Ditolak</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if($this->withdrawals->isEmpty())
            <div class="py-12 text-center">
                <flux:subheading>Tidak ada data penarikan</flux:subheading>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Sekolah</th>
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
                                    <div class="font-semibold">{{ $withdrawal->school->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $withdrawal->requestedBy->name }}</div>
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
                                    <div class="flex gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="viewDetail('{{ $withdrawal->id }}')">
                                            Detail
                                        </flux:button>

                                        @if($withdrawal->status === 'REQUESTED')
                                            <flux:button size="sm" variant="primary"
                                                wire:click="openApproveModal('{{ $withdrawal->id }}')">
                                                Setujui
                                            </flux:button>
                                            <flux:button size="sm" variant="danger"
                                                wire:click="openRejectModal('{{ $withdrawal->id }}')">
                                                Tolak
                                            </flux:button>
                                        @elseif($withdrawal->status === 'APPROVED')
                                            <flux:button size="sm" variant="primary"
                                                wire:click="openPayModal('{{ $withdrawal->id }}')">
                                                Bayar
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $this->withdrawals->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Detail Flyout --}}
    <flux:modal name="withdrawal-detail" flyout class="space-y-6">
        @if($selectedWithdrawal)
            <flux:heading size="lg" class="mb-4">Detail Penarikan</flux:heading>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:subheading class="text-xs text-zinc-500">Sekolah</flux:subheading>
                        <div class="font-semibold">{{ $selectedWithdrawal->school->name }}</div>
                    </div>
                    <div>
                        <flux:subheading class="text-xs text-zinc-500">Status</flux:subheading>
                        <flux:badge :color="$this->getStatusColor($selectedWithdrawal->status)">
                            {{ $this->getStatusLabel($selectedWithdrawal->status) }}
                        </flux:badge>
                    </div>
                </div>

                <flux:separator />

                <div>
                    <flux:subheading class="text-xs text-zinc-500 mb-2">Nominal Penarikan</flux:subheading>
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded">
                        <div class="text-2xl font-bold text-green-600">
                            Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}
                        </div>
                        <div class="text-sm text-zinc-500 mt-1">
                            Dana diterima PENUH tanpa potongan fee
                        </div>
                    </div>
                </div>

                @if($selectedWithdrawal->payment_proof && $selectedWithdrawal->status === 'PAID')
                    <div>
                        <flux:subheading class="text-xs text-zinc-500 mb-2">Bukti Transfer</flux:subheading>
                        <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded">
                            <img src="{{ asset('storage/' . $selectedWithdrawal->payment_proof) }}"
                                class="w-full max-h-48 rounded border cursor-pointer hover:opacity-80 transition object-contain bg-zinc-100 dark:bg-zinc-900"
                                alt="Bukti Transfer" onclick="window.open(this.src, '_blank')">
                            <div class="text-xs text-zinc-500 mt-2 flex items-center gap-1">
                                <flux:icon.eye class="w-4 h-4" />
                                Klik gambar untuk memperbesar
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <flux:subheading class="text-xs text-zinc-500 mb-2">Rekening Tujuan</flux:subheading>
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded space-y-1">
                        <div><strong>Bank:</strong> {{ $selectedWithdrawal->bank_name }}</div>
                        <div><strong>No. Rekening:</strong> {{ $selectedWithdrawal->account_number }}</div>
                        <div><strong>Pemilik:</strong> {{ $selectedWithdrawal->account_holder_name }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <flux:subheading class="text-xs text-zinc-500">Diminta Oleh</flux:subheading>
                        <div>{{ $selectedWithdrawal->requestedBy->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $selectedWithdrawal->requested_at->format('d M Y H:i') }}
                        </div>
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

    {{-- Approve Flyout --}}
    <flux:modal name="withdrawal-approve" flyout class="space-y-6">
        <flux:heading size="lg" class="mb-4">Konfirmasi Persetujuan</flux:heading>

        @if($selectedWithdrawal)
            <div class="mb-6">
                <p>Anda yakin ingin menyetujui penarikan dana sebesar:</p>
                <div class="text-2xl font-bold text-green-600 mt-2">
                    Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}
                </div>
                <p class="text-sm text-zinc-500 mt-1">
                    untuk {{ $selectedWithdrawal->school->name }}
                </p>
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="approveWithdrawal">Setujui</flux:button>
        </div>
    </flux:modal>

    {{-- Reject Flyout --}}
    <flux:modal name="withdrawal-reject" flyout class="space-y-6">
        <flux:heading size="lg" class="mb-4">Tolak Penarikan</flux:heading>

        <flux:field>
            <flux:label>Alasan Penolakan</flux:label>
            <flux:textarea wire:model="rejectionReason" placeholder="Masukkan alasan penolakan..." rows="4" />
            <flux:error name="rejectionReason" />
        </flux:field>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="rejectWithdrawal">Tolak</flux:button>
        </div>
    </flux:modal>

    {{-- Pay Flyout --}}
    <flux:modal name="withdrawal-pay" flyout class="space-y-6" x-on:close="$wire.cancelPayment()">
        <flux:heading size="lg" class="mb-4">Konfirmasi Pembayaran</flux:heading>

        @if($selectedWithdrawal)
            <div class="space-y-4 mb-6">
                <flux:card class="bg-blue-50 dark:bg-blue-900/20">
                    <div class="text-sm">
                        <div class="flex justify-between mb-2">
                            <span>Sekolah:</span>
                            <strong>{{ $selectedWithdrawal->school->name }}</strong>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Transfer ke Rekening:</span>
                            <strong>Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}</strong>
                        </div>
                        <div class="text-xs text-zinc-500 mt-2">
                            Dana ditransfer PENUH tanpa potongan
                        </div>
                    </div>
                </flux:card>

                <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded">
                    <div class="text-sm font-semibold mb-2">Rekening Tujuan:</div>
                    <div class="space-y-1 text-sm">
                        <div>{{ $selectedWithdrawal->bank_name }}</div>
                        <div>{{ $selectedWithdrawal->account_number }}</div>
                        <div>a.n. {{ $selectedWithdrawal->account_holder_name }}</div>
                    </div>
                </div>

                <flux:field>
                    <flux:label>Catatan Pembayaran (Opsional)</flux:label>
                    <flux:textarea wire:model="paymentNotes" placeholder="Contoh: No. Referensi Transfer, dll..."
                        rows="2" />
                </flux:field>

                {{-- Payment Proof Upload --}}
                <flux:field>
                    <flux:label>Bukti Transfer <span class="text-red-500">*</span></flux:label>
                    <flux:input type="file" wire:model="paymentProof" accept="image/*" />
                    <flux:error name="paymentProof" />
                    <flux:description>Upload screenshot/foto bukti transfer (JPG, PNG, max 2MB)</flux:description>

                    {{-- Preview --}}
                    @if($paymentProof)
                        <div class="mt-3">
                            <flux:subheading class="text-xs mb-2">Preview:</flux:subheading>
                            <img src="{{ $paymentProof->temporaryUrl() }}"
                                class="w-full max-h-48 rounded border object-contain bg-zinc-100 dark:bg-zinc-900"
                                alt="Preview">
                        </div>
                    @endif
                </flux:field>
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="cancelPayment">Batal</flux:button>
            <flux:button variant="primary" wire:click="markAsPaid">Konfirmasi Pembayaran</flux:button>
        </div>
    </flux:modal>
</div>