<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\EmailOtpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpVerificationController extends Controller
{
    /**
     * Verify the single-use OTP code.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email already verified.'], 200)
                : redirect()->intended(route('dashboard', absolute: false));
        }

        if (!$user->otp_code || $user->otp_code !== $request->otp_code) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Kode OTP tidak valid.'], 422);
            }
            return back()->withErrors(['otp_code' => 'Kode OTP tidak valid.']);
        }

        if ($user->otp_expires_at && now()->isAfter($user->otp_expires_at)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Kode OTP telah kedaluwarsa.'], 422);
            }
            return back()->withErrors(['otp_code' => 'Kode OTP telah kedaluwarsa.']);
        }

        $user->markEmailAsVerified();
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return $request->expectsJson()
            ? response()->json(['message' => 'Email berhasil diverifikasi.'], 200)
            : redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    /**
     * Resend a new OTP verification notification.
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email already verified.'], 200)
                : redirect()->intended(route('dashboard', absolute: false));
        }

        $user->sendEmailVerificationNotification();

        return $request->expectsJson()
            ? response()->json(['status' => 'otp-sent', 'message' => 'Kode OTP baru telah dikirim ke email Anda.'])
            : back()->with('status', 'verification-link-sent');
    }
}
