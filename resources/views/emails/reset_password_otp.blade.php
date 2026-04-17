<!DOCTYPE html>
<html>
<head>
    <title>Reset Password Prenava</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f7f9fc; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #424242;">Halo Bunda,</h2>
        <p style="color: #666666; line-height: 1.5;">
            Kami menerima permintaan untuk mereset password akun Prenava Anda. Silakan masukkan kode OTP berikut di aplikasi:
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="display: inline-block; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #FA6978; background-color: #FEECED; padding: 10px 20px; border-radius: 8px;">
                {{ $otp }}
            </span>
        </div>
        <p style="color: #666666; line-height: 1.5; font-size: 14px;">
            Kode ini berlaku selama 15 menit. Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini.
        </p>
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 30px 0;">
        <p style="color: #999999; font-size: 12px; text-align: center;">
            &copy; 2026 Prenava App. Hak cipta dilindungi.
        </p>
    </div>
</body>
</html>
