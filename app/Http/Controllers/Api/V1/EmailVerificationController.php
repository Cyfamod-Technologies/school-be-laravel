<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return $this->respond($request, Response::HTTP_UNPROCESSABLE_ENTITY, 'A verification token is required.', 'error');
        }

        $hashedToken = hash('sha256', $token);

        $record = EmailVerificationToken::with('user')
            ->where('token', $hashedToken)
            ->first();

        if (! $record) {
            return $this->respond($request, Response::HTTP_NOT_FOUND, 'This verification link is invalid or has already been used.', 'error');
        }

        if ($record->verified_at) {
            return $this->respond($request, Response::HTTP_OK, 'Email already verified. You can log in now.', 'success');
        }

        if ($record->isExpired()) {
            return $this->respond($request, Response::HTTP_GONE, 'This verification link has expired. Please request a new one.', 'error');
        }

        $user = $record->user;

        if (! $user) {
            return $this->respond($request, Response::HTTP_NOT_FOUND, 'User account for this verification link was not found.', 'error');
        }

        if (! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        $record->forceFill([
            'verified_at' => now(),
        ])->save();

        EmailVerificationToken::where('user_id', $user->id)
            ->whereNull('verified_at')
            ->where('id', '!=', $record->id)
            ->delete();

        return $this->respond($request, Response::HTTP_OK, 'Email verified successfully. You can now log in.', 'success');
    }

    protected function respond(Request $request, int $status, string $message, string $statusLabel): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        return response()->view('verification.result', [
            'status' => $statusLabel,
            'message' => $message,
        ], $status);
    }
}
