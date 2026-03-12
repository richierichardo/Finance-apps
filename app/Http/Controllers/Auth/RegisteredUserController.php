<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationOtp;
use App\Mail\OtpMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:'.User::class,
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => \App\Enums\UserRole::Member->value,
            'status' => \App\Enums\UserStatus::Active->value,
            'email_verified_at' => null,
        ]);

        event(new Registered($user));

        // Generate and send OTP
        $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $otp = EmailVerificationOtp::create([
            'email' => $user->email,
            'user_id' => $user->id,
            'otp_code' => $otp_code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp, $user->name));

        session(['registration_email' => $user->email]);

        return redirect()->route('otp.verify');
    }
}
