<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordOtpMail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * Mengirimkan OTP ke alamat email
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $email = $request->email;
        // Generate 6 digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Simpan ke DB / Update jika sudah ada
        DB::table('password_reset_otps')->updateOrInsert(
            ['email' => $email],
            [
                'otp' => Hash::make($otp),
                'expires_at' => Carbon::now()->addMinutes(15),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        );

        // Setup testing environment if SMTP is not ready
        try {
            Mail::to($email)->send(new ResetPasswordOtpMail($otp));
            return response()->json([
                'status' => 'success',
                'message' => 'OTP telah dikirim ke email Anda.'
            ]);
        } catch (\Exception $e) {
            // Karena ini masih development dan jika SMTP error, kita return OTP-nya (JANGAN DI PRODUCTION)
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim email. (Dev mode: OTP anda adalah ' . $otp . ')',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memverifikasi OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6'
        ]);

        $record = DB::table('password_reset_otps')->where('email', $request->email)->first();

        if (!$record) {
            return response()->json(['status' => 'error', 'message' => 'OTP tidak ditemukan. Silakan kirim ulang.'], 400);
        }

        if (Carbon::now()->isAfter($record->expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'OTP telah kedaluwarsa. Silakan kirim ulang.'], 400);
        }

        if (!Hash::check($request->otp, $record->otp)) {
            return response()->json(['status' => 'error', 'message' => 'OTP tidak valid.'], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP berhasil diverifikasi.',
            'reset_token' => encrypt($request->email . '|' . Carbon::now()->timestamp) // Temporary token for resetting
        ]);
    }

    /**
     * Mereset Password dengan reset_token yang valid
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            $decrypted = decrypt($request->reset_token);
            list($tokenEmail, $timestamp) = explode('|', $decrypted);

            if ($tokenEmail !== $request->email) {
                return response()->json(['status' => 'error', 'message' => 'Token tidak valid untuk email ini.'], 400);
            }

            // Token expires in 30 minutes
            if (Carbon::createFromTimestamp($timestamp)->addMinutes(30)->isPast()) {
                return response()->json(['status' => 'error', 'message' => 'Sesi reset password telah berakhir.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Hapus record OTP setelah berhasil
        DB::table('password_reset_otps')->where('email', $request->email)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diubah. Silakan login dengan password baru.'
        ]);
    }
}
