<?php

use App\Models\Student;
use App\Models\StudentCard;
use App\Models\WalletTransaction;
use App\Services\TransactionService;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new #[Lazy] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                    <div class="h-4 w-96 mt-2 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6">
                <div class="space-y-6">
                    <div class="p-8 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-6">
                        <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg animate-pulse"></div>
                        <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-xl animate-pulse"></div>
                        <div class="h-12 bg-zinc-200 dark:bg-zinc-700 rounded-lg animate-pulse"></div>
                    </div>
                </div>
                <div>
                    <div class="p-6 rounded-xl border border-blue-100 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-900/10 h-48 animate-pulse"></div>
                </div>
            </div>
        </div>
        HTML;
    }
    #[Validate('required')]
    public $cardNumber = '';

    #[Validate('required')]
    public $amount = '';

    #[Validate('required|string|min:4|max:6')]
    public $pin = '';

    public $student = null;

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
            ->first();

        $this->student = $card ? $card->student : null;

        if ($this->student) {
            // Memberi tahu frontend bahwa student ditemukan untuk pindah fokus ke Amount
            $this->dispatch('student-found');
        }
    }

    public function processPurchase(TransactionService $service)
    {
        $this->validate();

        if (!$this->student) {
            Flux::toast('Santri atau kartu tidak ditemukan.', variant: 'danger');
            return;
        }

        if (!\Illuminate\Support\Facades\Hash::check($this->pin, $this->student->wallet_pin)) {
            Flux::toast('PIN yang dimasukkan salah.', variant: 'danger');
            $this->pin = '';
            $this->dispatch('focus-pin');
            return;
        }

        try {
            $rawAmount = (float) str_replace('.', '', $this->amount);

            if ($rawAmount < 1) {
                Flux::toast('Nominal pembelian tidak valid.', variant: 'danger');
                return;
            }

            $service->processPurchase($this->student, $rawAmount, $this->pin, auth()->user());

            Flux::toast('Pembelian berhasil diproses.', variant: 'success');

            // Reset state
            $this->cardNumber = '';
            $this->amount = '';
            $this->pin = '';
            $this->student = null;

            // Memberi tahu frontend untuk kembali fokus ke Scan Kartu
            $this->dispatch('transaction-reset');
        } catch (\Exception $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }
}; ?>

<div class="w-full" x-data="{
        focusCard() { 
            this.$nextTick(() => {
                const el = this.$refs.cardInput?.querySelector('input');
                if (el) el.focus();
            });
        },
        focusAmount() { 
            this.$nextTick(() => {
                const el = this.$refs.amountInput?.querySelector('input');
                if (el) el.focus();
            });
        },
        focusPin() { 
            this.$nextTick(() => {
                // Timeout kecil memastikan elemen OTP selesai render oleh Livewire
                setTimeout(() => {
                    const el = this.$refs.pinInput?.querySelector('input');
                    if (el) el.focus();
                }, 50);
            });
        }
    }" x-init="focusCard()" @student-found.window="focusAmount()" @transaction-reset.window="focusCard()"
    @focus-pin.window="focusPin()">

    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Point of Sale (POS)') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">
                    {{ __('Transaksi pembelian santri menggunakan saldo wallet') }}
                </flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6">
        <div class="space-y-6">
            <flux:card>
                <div class="space-y-6">
                    <flux:input wire:model.live.debounce.500ms="cardNumber" type="number" inputmode="numeric"
                        label="Scan Kartu / Input Nomor Kartu" placeholder="Scan kartu santri di sini..." icon="qr-code"
                        x-ref="cardInput" @keydown.enter.prevent="focusAmount()" clearable />

                    @if ($student)
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-16 h-16 rounded-lg bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center">
                                    <flux:icon.user class="text-zinc-500" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-bold text-lg">{{ $student->full_name }}</h3>
                                        <flux:badge :variant="$student->status === 'active' ? 'success' : 'danger'"
                                            size="sm">
                                            {{ strtoupper($student->status) }}
                                        </flux:badge>
                                    </div>
                                    <p class="text-sm text-zinc-500">NIS: {{ $student->nis }}</p>
                                    <div class="mt-2 text-sm">
                                        Saldo: <span class="font-bold text-zinc-900 dark:text-white">
                                            Rp {{ number_format($student->wallet_balance, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-2 border-t dark:border-zinc-800">
                            <flux:input wire:model.live="amount" label="Nominal Pembelian" type="text" inputmode="numeric"
                                prefix="Rp" placeholder="0" x-ref="amountInput"
                                x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                                @keydown.enter.prevent="focusPin()" />

                            <flux:field>
                                <flux:label>PIN Santri <span class="text-red-500">*</span></flux:label>
                                <div class="flex justify-center py-2" x-ref="pinInput">
                                    <flux:otp wire:model="pin" length="6" name="pin" />
                                </div>
                                <flux:error name="pin" />
                                <flux:description class="text-amber-600 dark:text-amber-400 text-center">
                                    ⚠️ Jangan lihat saat santri mengetik PIN
                                </flux:description>
                            </flux:field>

                            <div
                                class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 rounded-lg">
                                <div class="flex gap-3 text-xs">
                                    <flux:icon.exclamation-triangle
                                        class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                    <div class="text-amber-800 dark:text-amber-200">
                                        <strong>Keamanan:</strong> Admin tidak boleh melihat PIN santri.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <flux:button wire:click="processPurchase" variant="primary" class="w-full"
                                :disabled="!$amount || !$pin" loading-target="processPurchase">
                                Proses Pembayaran
                            </flux:button>
                        </div>
                    @elseif($cardNumber)
                        <div
                            class="p-8 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                            <flux:icon.user-minus class="mx-auto mb-2 text-zinc-400" />
                            <p class="text-zinc-500">Kartu dengan nomor "{{ $cardNumber }}" tidak ditemukan.</p>
                        </div>
                    @else
                        <div class="p-12 text-center">
                            <flux:icon.credit-card class="mx-auto mb-4 w-12 h-12 text-zinc-300" />
                            <p class="text-zinc-500 text-sm">
                                Scan kartu atau masukkan nomor kartu santri untuk memulai transaksi.
                            </p>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

        <div>
            <flux:card class="bg-blue-50/50 dark:bg-blue-900/10 border-blue-100 dark:border-blue-900/50">
                <div class="flex gap-4">
                    <flux:icon.information-circle class="text-blue-500 shrink-0" />
                    <div class="space-y-2">
                        <h4 class="font-bold text-blue-900 dark:text-blue-100">Instruksi Kasir</h4>
                        <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-2 list-disc pl-4">
                            <li>Kursor otomatis fokus ke scan kartu saat halaman dibuka.</li>
                            <li>Setelah kartu terdeteksi, kursor pindah ke nominal secara otomatis.</li>
                            <li>Tekan <strong>Enter</strong> di nominal untuk pindah ke PIN.</li>
                            <li>Sistem akan mendeteksi input scanner (Enter otomatis) untuk mempercepat proses.</li>
                        </ul>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>