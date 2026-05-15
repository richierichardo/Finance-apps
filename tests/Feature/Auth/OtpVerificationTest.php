<?php

use App\Enums\UserRole;
use App\Mail\OtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('verify otp page redirects to register when no email in session or query', function () {
    $response = $this->get(route('otp.verify'));

    $response->assertRedirect(route('register', absolute: false));
});

test('verify otp page renders when session has registration email', function () {
    $response = $this->withSession(['registration_email' => 'otp-page@example.com'])
        ->get(route('otp.verify'));

    $response->assertStatus(200);
});

test('send otp creates record and sends mail', function () {
    Mail::fake();

    User::factory()->create(['email' => 'sendotp@example.com']);

    $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
        ->postJson(route('otp.send'), ['email' => 'sendotp@example.com']);

    $response->assertOk()
        ->assertJsonPath('email', 'sendotp@example.com');

    expect(EmailVerificationOtp::where('email', 'sendotp@example.com')->count())->toBe(1);

    Mail::assertSent(OtpMail::class);
});

test('verify otp with invalid code returns validation error', function () {
    User::factory()->create(['email' => 'badcode@example.com']);

    EmailVerificationOtp::create([
        'email' => 'badcode@example.com',
        'user_id' => User::where('email', 'badcode@example.com')->value('id'),
        'otp_code' => '111111',
        'attempts' => 0,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->from(route('otp.verify'))
        ->post('/verify-otp', [
            'email' => 'badcode@example.com',
            'otp_code' => '999999',
        ]);

    $response->assertSessionHasErrors('otp_code');
});

test('verify otp with expired code returns validation error', function () {
    $user = User::factory()->create(['email' => 'expired@example.com']);

    EmailVerificationOtp::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'otp_code' => '222222',
        'attempts' => 0,
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->from(route('otp.verify'))
        ->post('/verify-otp', [
            'email' => $user->email,
            'otp_code' => '222222',
        ]);

    $response->assertSessionHasErrors('otp_code');
});

test('verify otp success verifies email logs in and redirects member to dashboard', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'verifyok@example.com',
        'role' => UserRole::Member,
    ]);

    EmailVerificationOtp::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'otp_code' => '333333',
        'attempts' => 0,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->post('/verify-otp', [
        'email' => $user->email,
        'otp_code' => '333333',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
    expect(EmailVerificationOtp::where('email', $user->email)->count())->toBe(0);
});

test('verify otp success redirects admin to admin dashboard', function () {
    $user = User::factory()->unverified()->admin()->create([
        'email' => 'adminotp@example.com',
    ]);

    EmailVerificationOtp::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'otp_code' => '444444',
        'attempts' => 0,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->post('/verify-otp', [
        'email' => $user->email,
        'otp_code' => '444444',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('increment otp attempt returns exceeded when attempts reach limit', function () {
    $user = User::factory()->create(['email' => 'attempts@example.com']);

    $otp = EmailVerificationOtp::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'otp_code' => '555555',
        'attempts' => 4,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/increment-otp-attempt', [
        'email' => $user->email,
    ]);

    $response->assertOk()
        ->assertJsonPath('exceeded', true);

    $otp->refresh();
    expect((int) $otp->attempts)->toBe(5);
});

test('unverified member login is redirected to otp verify', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'needverify@example.com',
    ]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('otp.verify', absolute: false));
    $this->assertGuest();
});
