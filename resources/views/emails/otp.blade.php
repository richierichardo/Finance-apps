<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #333;">Email Verification</h2>
    
    <p>Hi {{ $name }},</p>
    
    <p>Thank you for registering. To verify your email address, please use the following OTP code:</p>
    
    <div style="background-color: #f5f5f5; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px;">
        <h1 style="margin: 0; letter-spacing: 5px; color: #2563eb;">{{ $otp_code }}</h1>
    </div>
    
    <p style="color: #666;">This code will expire in <strong>{{ $expires_in_minutes }} minutes</strong>.</p>
    
    <p style="color: #666;">If you did not request this code, please ignore this email.</p>
    
    <p>Best regards,<br>The Team</p>
    
    <hr style="border: none; border-top: 1px solid #ddd; margin-top: 40px;">
    <p style="color: #999; font-size: 12px;">This is an automated email. Please do not reply to this message.</p>
</div>
