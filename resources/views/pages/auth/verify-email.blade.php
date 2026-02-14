<div class="mt-4 flex flex-col gap-6">
    <flux:heading size="lg" class="text-center">{{ __('Verifikasi Email') }}</flux:heading>
    <flux:text class="text-center">
        {{ __('Silakan masukkan 6 digit kode OTP yang kami kirimkan ke email Anda.') }}
    </flux:text>

    <form wire:submit="verify" class="space-y-6">
        <div class="flex justify-center">
            <flux:otp wire:model="otp_code" length="6" autofocus />
        </div>

        <div class="flex justify-center">
             <flux:error name="otp_code" />
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Verifikasi Email') }}
        </flux:button>
    </form>

    <div class="flex flex-col items-center justify-between space-y-4">
        <div class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Tidak menerima kode?') }}
            <button wire:click="resend" class="text-zinc-800 dark:text-zinc-200 hover:underline cursor-pointer font-medium">
                {{ __('Kirim ulang') }}
            </button>
        </div>

        <button wire:click="logout" class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 cursor-pointer transition">
            {{ __('Log out') }}
        </button>
    </div>
</div>