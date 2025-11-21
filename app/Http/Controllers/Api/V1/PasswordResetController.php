<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ResetPassword;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        // Always respond successfully to avoid leaking which emails exist.
        if (! $user) {
            return response()->json([
                'message' => 'If an account exists for this email, a password reset link has been sent.',
            ]);
        }

        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $expiresMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::now()->addMinutes(max($expiresMinutes, 5));

        DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ]
        );

        $resetUrl = url('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email));

        try {
            Mail::to($user->email)->send(new ResetPassword($user, $resetUrl, $expiresAt));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'If an account exists for this email, a password reset link has been sent.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $record = DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
            ->where('email', $validated['email'])
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $hashedToken = hash('sha256', $validated['token']);

        if (! hash_equals($record->token, $hashedToken)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $expiresMinutes = (int) config('auth.passwords.users.expire', 60);
        $createdAt = Carbon::parse($record->created_at);

        if ($createdAt->addMinutes(max($expiresMinutes, 5))->isPast()) {
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $validated['email'])
                ->delete();

            throw ValidationException::withMessages([
                'token' => ['This password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User account for this email was not found.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
            ->where('email', $validated['email'])
            ->delete();

        return response()->json([
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
        ]);
    }
}

