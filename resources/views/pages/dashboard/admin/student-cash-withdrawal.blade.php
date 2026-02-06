<?php

use App\Models\StudentCard;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    // UI State
    public $step = 1; // 1=scan, 2=preview, 3=input amount/pin

    public $cardNumber = '';

    public $card = null;

    public $student = null;

    public $studentBalance = 0;

    public $todayWithdrawals = 0;

    public $remainingLimit = 0;
    public $trustFundPool = 0; // Wallet B: Net cash available from top-ups

    // Form inputs
    public $amount = '';

    public $pin = '';

    // Computed: Get school ID
    public function getSchoolIdProperty()
    {
        return auth()->user()->school_id;
    }

    // Step 1: Search by card number
    public function searchCard()
    {
        $this->validate([
            'cardNumber' => 'required|string|min:5',
        ]);

        // Find card with student
        $this->card = StudentCard::where('card_number', $this->cardNumber)
            ->with(['student'])
            ->first();

        if (!$this->card) {
            session()->flash('error', 'Kartu tidak ditemukan');

            return;
        }

        // Validate card status
        if ($this->card->status !== 'printed') {
            session()->flash('error', 'Kartu belum dicetak atau belum aktif');

            return;
        }

        // Validate card expiry
        if ($this->card->expires_at && $this->card->expires_at < now()) {
            session()->flash('error', 'Kartu sudah expired pada ' . $this->card->expires_at->format('d M Y'));

            return;
        }

        $this->student = $this->card->student;

        // CRITICAL: School isolation check (defense-in-depth)
        if ($this->student->school_id !== $this->schoolId) {
            abort(403, 'Unauthorized: Cross-school access detected');
        }

        // Validate student status
        if ($this->student->status !== 'ACTIVE') {
            session()->flash('error', 'Santri tidak aktif');

            return;
        }

        if (!$this->student->allow_transaction) {
            session()->flash('error', 'Transaksi santri sedang diblokir');

            return;
        }

        if (!$this->student->wallet_pin) {
            session()->flash('error', 'PIN belum diatur untuk santri ini. Silakan hubungi administrator.');

            return;
        }

        // Calculate balance and limits
        $this->calculateBalance();
        $this->step = 2;
    }

    // Calculate current balance and daily limit
    private function calculateBalance()
    {
        $this->studentBalance = WalletTransaction::where('student_id', $this->student->id)
            ->where('status', 'PAID')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = "CREDIT" THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = "DEBIT" THEN amount ELSE 0 END), 0) as balance
            ')
            ->value('balance') ?? 0;

        $this->todayWithdrawals = WalletTransaction::where('student_id', $this->student->id)
            ->where('category', 'WITHDRAWAL')
            ->where('status', 'PAID')
            ->whereDate('created_at', today())
            ->sum('amount') ?? 0;

        $this->remainingLimit = $this->student->daily_cash_limit - $this->todayWithdrawals;

        // WALLET B PROTECTION: Calculate net trust fund cash available
        $totalTopUp = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->sum('amount');

        $totalWithdrawnSantri = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'DEBIT')
            ->where('category', 'WITHDRAWAL')
            ->where('status', 'PAID')
            ->sum('amount');

        $this->trustFundPool = $totalTopUp - $totalWithdrawnSantri;
    }

    // Process withdrawal
    public function processWithdrawal()
    {
        // Validate inputs
        $this->validate([
            'amount' => 'required|numeric|min:5000',
            'pin' => 'required|string|min:4|max:6',
        ], [
            'amount.required' => 'Masukkan nominal penarikan',
            'amount.min' => 'Nominal minimal Rp 5.000',
            'pin.required' => 'PIN wajib diisi',
            'pin.min' => 'PIN minimal 4 digit',
        ]);

        try {
            // CRITICAL: Wrap in DB transaction to prevent race condition
            DB::transaction(function () {
                // Re-calculate balance inside transaction (prevent race condition)
                $this->calculateBalance();

                // CRITICAL: School isolation check (defense-in-depth)
                if ($this->student->school_id !== $this->schoolId) {
                    abort(403, 'Unauthorized: Cross-school access detected');
                }

                // CRITICAL: Strict Wallet B check (Trust Fund liquidity)
                if ($this->amount > $this->trustFundPool) {
                    throw ValidationException::withMessages([
                        'amount' => 'Kas dana titipan tidak mencukupi untuk penarikan tunai ini.',
                    ]);
                }

                // Validate amount against balance
                if ($this->amount > $this->studentBalance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo tidak mencukupi. Saldo: Rp ' . number_format($this->studentBalance, 0, ',', '.'),
                    ]);
                }

                // Validate amount against daily limit
                if (($this->todayWithdrawals + $this->amount) > $this->student->daily_cash_limit) {
                    throw ValidationException::withMessages([
                        'amount' => 'Melebihi limit harian. Sisa limit: Rp ' . number_format($this->remainingLimit, 0, ',', '.'),
                    ]);
                }

                // CRITICAL: Validate PIN last (after all other checks pass)
                if (!Hash::check($this->pin, $this->student->wallet_pin)) {
                    throw ValidationException::withMessages(['pin' => 'PIN salah']);
                }

                // Generate secure invoice code (with student_id for uniqueness)
                $invoiceCode = 'WD-CASH-' . $this->student->id . '-' . now()->format('YmdHis');

                // Create transaction
                WalletTransaction::create([
                    'school_id' => $this->schoolId,
                    'student_id' => $this->student->id,
                    'pic_user_id' => auth()->id(),
                    'invoice_code' => $invoiceCode,
                    'type' => 'DEBIT',
                    'category' => 'WITHDRAWAL',
                    'amount' => $this->amount,
                    'payment_method' => 'cash',
                    'status' => 'PAID',
                    'description' => 'Penarikan uang saku via kartu santri',
                    'admin_fee' => 0,
                    'payment_fee' => 0,
                ]);
            });

            // Success message
            session()->flash('message', 'Penarikan berhasil! Serahkan uang tunai Rp ' . number_format($this->amount, 0, ',', '.') . ' kepada ' . $this->student->full_name);

            // CRITICAL: Clear PIN from memory (security hygiene)
            $this->pin = null;

            // Reset form
            $this->reset(['step', 'cardNumber', 'card', 'student', 'studentBalance', 'todayWithdrawals', 'remainingLimit', 'amount']);

        } catch (ValidationException $e) {
            // Clear PIN on validation error too
            $this->pin = null;
            throw $e;
        }
    }

    // Reset to start
    public function resetForm()
    {
        // Clear PIN when resetting
        $this->pin = null;
        $this->reset(['step', 'cardNumber', 'card', 'student', 'studentBalance', 'todayWithdrawals', 'remainingLimit', 'amount']);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">Penarikan Uang Saku Santri</flux:heading>
        <flux:subheading>Scan kartu atau input nomor kartu untuk proses penarikan tunai</flux:subheading>
    </div>

    <!-- Success Message -->
    @if (session('message'))
        <flux:card class="bg-green-50 dark:bg-green-900/20 border-green-200">
            <div class="flex items-center gap-3">
                <flux:icon.check-circle class="w-6 h-6 text-green-600" />
                <div class="flex-1">
                    <flux:heading size="sm" class="text-green-800 dark:text-green-200">{{ session('message') }}
                    </flux:heading>
                    <flux:button size="sm" variant="ghost" wire:click="resetForm" class="mt-2">
                        Transaksi Baru
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Error Message -->
    @if (session('error'))
        <flux:card class="bg-red-50 dark:bg-red-900/20 border-red-200">
            <div class="flex items-center gap-3">
                <flux:icon.exclamation-circle class="w-6 h-6 text-red-600" />
                <div class="flex-1">
                    <flux:heading size="sm" class="text-red-600">{{ session('error') }}</flux:heading>
                    <flux:button size="sm" variant="ghost" wire:click="resetForm" class="mt-2">
                        Coba Lagi
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Step 1: Card Input -->
    @if ($step === 1)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Identifikasi Santri</flux:heading>

            <form wire:submit="searchCard">
                <flux:field>
                    <flux:label>Nomor Kartu</flux:label>
                    <flux:input wire:model="cardNumber" placeholder="Scan atau ketik nomor kartu" autofocus />
                    <flux:error name="cardNumber" />
                    <flux:description>
                        Gunakan scanner barcode atau ketik manual
                    </flux:description>
                </flux:field>

                <div class="mt-4">
                    <flux:button type="submit" variant="primary">Cari Santri</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <!-- Step 2: Student Preview -->
    @if ($step === 2 && $student)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Data Santri</flux:heading>

            <div class="space-y-4">
                <!-- Student Info -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:subheading class="text-xs text-zinc-500 mb-1">Nama Santri</flux:subheading>
                        <flux:heading size="sm">{{ $student->full_name }}</flux:heading>
                    </div>
                    <div>
                        <flux:subheading class="text-xs text-zinc-500 mb-1">NIS</flux:subheading>
                        <flux:heading size="sm">{{ $student->nis }}</flux:heading>
                    </div>
                </div>

                <!-- Balance & Limit -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                        <flux:subheading class="text-xs text-green-600 dark:text-green-400 mb-1">Saldo Wallet
                        </flux:subheading>
                        <flux:heading size="xl" class="text-green-600 dark:text-green-400">
                            Rp {{ number_format($studentBalance, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <flux:subheading class="text-xs text-blue-600 dark:text-blue-400 mb-1">Sisa Limit Harian
                        </flux:subheading>
                        <flux:heading size="xl" class="text-blue-600 dark:text-blue-400">
                            Rp {{ number_format($remainingLimit, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>

                <!-- Status Indicators -->
                <div class="flex gap-2">
                    <flux:badge color="green">Kartu: {{ ucfirst($card->status) }}</flux:badge>
                    <flux:badge color="blue">Santri: {{ ucfirst($student->status) }}</flux:badge>
                    @if($student->allow_transaction)
                        <flux:badge color="green">Transaksi: Aktif</flux:badge>
                    @else
                        <flux:badge color="red">Transaksi: Diblokir</flux:badge>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-4 border-t dark:border-zinc-700">
                    <flux:button wire:click="$set('step', 3)" variant="primary">
                        Lanjutkan Penarikan
                    </flux:button>
                    <flux:button wire:click="resetForm" variant="ghost">
                        Batal
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Step 3: Amount & PIN Input -->
    @if ($step === 3 && $student)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Input Penarikan</flux:heading>

            <form wire:submit="processWithdrawal">
                <div class="space-y-4">
                    <!-- Amount -->
                    <flux:field>
                        <flux:label>Nominal Penarikan</flux:label>
                        <flux:input wire:model="amount" type="number" placeholder="Masukkan nominal" min="5000"
                            step="1000" />
                        <flux:error name="amount" />
                        <flux:description>
                            Minimal Rp 5.000 | Saldo: Rp {{ number_format($studentBalance, 0, ',', '.') }} | Limit: Rp
                            {{ number_format($remainingLimit, 0, ',', '.') }}
                        </flux:description>
                    </flux:field>

                    <!-- PIN Input -->
                    <flux:field>
                        <flux:label>PIN Santri <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="pin" type="password" placeholder="Minta santri input PIN" maxlength="6"
                            inputmode="numeric" />
                        <flux:error name="pin" />
                        <flux:description class="text-amber-600 dark:text-amber-400">
                            ⚠️ Jangan lihat saat santri mengetik PIN
                        </flux:description>
                    </flux:field>

                    <!-- Warning Alert -->
                    <div
                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 rounded-lg">
                        <div class="flex gap-3">
                            <flux:icon.exclamation-triangle
                                class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                            <div class="text-sm text-amber-800 dark:text-amber-200">
                                <strong>Penting:</strong> Admin tidak boleh melihat atau mengetahui PIN santri.
                                Pastikan santri menginput PIN sendiri.
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4">
                        <flux:button type="submit" variant="primary">
                            Proses Penarikan
                        </flux:button>
                        <flux:button wire:click="$set('step', 2)" variant="ghost" type="button">
                            Kembali
                        </flux:button>
                        <flux:button wire:click="resetForm" variant="ghost" type="button">
                            Batal
                        </flux:button>
                    </div>
                </div>
            </form>
        </flux:card>
    @endif
</div>