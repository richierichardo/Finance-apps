<?php

use App\Mail\OtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and are redirected to OTP verification', function () {
    Mail::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser_reg',
        'email' => 'register-test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertRedirect(route('otp.verify', absolute: false));
    $this->assertGuest();

    $this->assertDatabaseHas('users', [
        'email' => 'register-test@example.com',
        'username' => 'testuser_reg',
        'email_verified_at' => null,
    ]);

    $user = User::where('email', 'register-test@example.com')->first();
    expect($user)->not->toBeNull();

    $otp = EmailVerificationOtp::where('email', 'register-test@example.com')->first();
    expect($otp)->not->toBeNull()
        ->and($otp->otp_code)->toHaveLength(6);

    Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('registration requires unique username and email', function () {
    Mail::fake();

    User::factory()->create([
        'username' => 'taken_name',
        'email' => 'taken@example.com',
    ]);

    $response = $this->from('/register')->post('/register', [
        'name' => 'Other',
        'username' => 'taken_name',
        'email' => 'taken@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertSessionHasErrors(['username', 'email']);
});
