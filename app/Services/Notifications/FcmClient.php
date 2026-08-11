<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmClient
{
    /** @return array{sent: bool, invalid_token: bool, error: string|null} */
    public function send(string $deviceToken, string $title, string $body, array $data = []): array
    {
        if (! config('services.firebase.enabled')) {
            return ['sent' => false, 'invalid_token' => false, 'error' => 'Firebase push notifications are not configured.'];
        }

        $projectId = (string) config('services.firebase.project_id');

        try {
            $response = Http::asJson()
                ->withToken($this->accessToken())
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => collect($data)->mapWithKeys(
                            fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null
                                ? (string) ($value ?? '')
                                : json_encode($value, JSON_UNESCAPED_SLASHES)]
                        )->all(),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'student_updates',
                                'sound' => 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => ['sound' => 'default'],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return ['sent' => false, 'invalid_token' => false, 'error' => $exception->getMessage()];
        }

        if ($response->successful()) {
            return ['sent' => true, 'invalid_token' => false, 'error' => null];
        }

        $errorCode = (string) $response->json('error.details.0.errorCode', '');
        $message = (string) $response->json('error.message', 'FCM rejected the notification.');

        return [
            'sent' => false,
            'invalid_token' => in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true),
            'error' => $message,
        ];
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'firebase.oauth.'.hash('sha256', (string) $credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            $now = time();
            $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the Firebase service-account assertion.');
            }

            $assertion = "{$header}.{$claims}.{$this->base64UrlEncode($signature)}";
            $response = Http::asForm()->timeout(15)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                throw new RuntimeException((string) $response->json('error_description', 'Unable to obtain a Firebase access token.'));
            }

            return (string) $response->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string, token_uri?: string} */
    private function credentials(): array
    {
        $path = (string) config('services.firebase.credentials');

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS must point to a readable service-account JSON file.');
        }

        $credentials = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('The Firebase service-account file is invalid.');
        }

        return $credentials;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
