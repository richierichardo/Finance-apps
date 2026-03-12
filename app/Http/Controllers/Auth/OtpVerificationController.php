<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpVerificationController extends Controller
{
    /**
     * Show OTP verification page
     */
    public function show(Request $request)
    {
        $email = $request->query('email') ?? session('registration_email');
        
        if (!$email) {
            return redirect()->route('register');
        }

        return inertia('Auth/VerifyOtp', [
            'email' => $email,
        ]);
    }

    /**
     * Send OTP to email
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $validated['email'];
        $user = User::where('email', $email)->first();

        // Generate 6-digit OTP
        $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Delete previous OTP for this email
        EmailVerificationOtp::where('email', $email)->delete();

        // Create new OTP
        $otp = EmailVerificationOtp::create([
            'email' => $email,
            'user_id' => $user?->id,
            'otp_code' => $otp_code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send email
        Mail::to($email)->send(new OtpMail($otp, $user?->name ?? 'User'));

        return response()->json([
            'message' => 'OTP has been sent to your email',
            'email' => $email,
        ]);
    }

    /**
     * Verify OTP
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $otp = EmailVerificationOtp::where('email', $validated['email'])
            ->where('otp_code', $validated['otp_code'])
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'otp_code' => 'Invalid OTP code.',
            ]);
        }

        // Check if expired
        if (!$otp->isValid()) {
            throw ValidationException::withMessages([
                'otp_code' => 'OTP has expired. Please resend OTP.',
            ]);
        }

        // Check attempt limit
        if ($otp->isAttemptExceeded()) {
            $otp->delete();
            throw ValidationException::withMessages([
                'otp_code' => 'Too many attempts. Please resend OTP.',
            ]);
        }

        // Mark as verified and delete OTP
        $user = User::where('email', $validated['email'])->first();
        
        if ($user) {
            $user->update(['email_verified_at' => now()]);
            Auth::login($user);
            $otp->delete();

            return redirect($user->role->value === 'admin' ? '/admin/dashboard' : '/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => 'User not found.',
        ]);
    }

    /**
     * Increment OTP attempts
     */
    public function incrementAttempt(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $otp = EmailVerificationOtp::where('email', $validated['email'])->first();

        if ($otp) {
            $otp->increment('attempts');

            if ($otp->isAttemptExceeded()) {
                return response()->json([
                    'message' => 'Too many attempts. Please resend OTP.',
                    'exceeded' => true,
                ]);
            }
        }

        return response()->json(['succeeded' => true]);
    }
}