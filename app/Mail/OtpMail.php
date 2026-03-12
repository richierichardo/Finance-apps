<?php

namespace App\Mail;

use App\Models\EmailVerificationOtp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailVerificationOtp $otp,
        public string $name = 'User'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi Email - Kode OTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otp_code' => $this->otp->otp_code,
                'name' => $this->name,
                'expires_in_minutes' => 15,
            ],
        );
    }
}
