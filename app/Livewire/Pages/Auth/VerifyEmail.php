<?php

namespace App\Livewire\Pages\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class VerifyEmail extends Component
{
    public string $otp_code = '';

    public function mount()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
        }
    }

    public function verify()
    {
        $this->validate([
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        if (! $user->otp_code || $user->otp_code !== $this->otp_code) {
            throw ValidationException::withMessages([
                'otp_code' => __('Kode OTP tidak valid.'),
            ]);
        }

        if ($user->otp_expires_at && now()->isAfter($user->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp_code' => __('Kode OTP telah kedaluwarsa.'),
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        $this->redirectIntended(default: route('dashboard', absolute: false).'?verified=1', navigate: true);
    }

    public function resend()
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        $user->sendEmailVerificationNotification();

        $this->dispatch('verification-link-sent');
        
        flux()->toast(text: __('Kode OTP baru telah dikirim ke email Anda.'), variant: 'success');
    }

    public function logout(\App\Livewire\Actions\Logout $logout)
    {
        $logout();
        
        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('pages.auth.verify-email');
    }
}
