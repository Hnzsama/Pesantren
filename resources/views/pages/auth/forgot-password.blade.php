<div class="flex flex-col gap-6">
    <flux:heading size="lg" class="text-center">{{ __('Forgot password') }}</flux:heading>
    <flux:text class="text-center">
        {{ __('Enter your email to receive a password reset link.') }}
    </flux:text>

    <form wire:submit="sendResetLink" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            :label="__('Email Address')"
            type="email"
            required
            autofocus
            placeholder="email@example.com"
        />

        <flux:button variant="primary" type="submit" class="w-full">
            {{ __('Email password reset link') }}
        </flux:button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-500 dark:text-zinc-400">
        <span>{{ __('Or, return to') }}</span>
        <a href="{{ route('login') }}" class="text-zinc-800 dark:text-zinc-200 hover:underline cursor-pointer font-medium" wire:navigate>
            {{ __('log in') }}
        </a>
    </div>
</div>
