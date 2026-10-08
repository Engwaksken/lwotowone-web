<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        $credentials = $this->fcmCredentials();
        $projectId = config('services.fcm.project_id') ?: ($credentials['project_id'] ?? null);

        if (! $token || ! $projectId || ! $credentials) {
            Log::warning('FCM credentials, project ID or device token are missing.');

            return ['sent' => false, 'error' => 'missing_config'];
        }

        $accessToken = $this->fcmAccessToken($credentials);
        if (! $accessToken) {
            return ['sent' => false, 'error' => 'authentication_failed'];
        }

        $messageData = ['title' => $title, 'body' => $body];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $messageData[$key] = (string) $value;
            }
        }

        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $messageData,
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::error('FCM request failed.', ['exception' => $exception->getMessage()]);

            return ['sent' => false, 'error' => 'provider_unavailable'];
        }

        if ($response->failed()) {
            $unregistered = $response->json('error.details.0.errorCode') === 'UNREGISTERED';
            Log::warning('FCM rejected a message.', [
                'status' => $response->status(),
                'unregistered' => $unregistered,
                'error' => $response->json('error.status'),
            ]);

            return ['sent' => false, 'error' => $unregistered ? 'unregistered' : 'provider_error'];
        }

        return ['sent' => true, 'response' => $response->json()];
    }

    public function sendSms(string $to, string $body, ?string $from = null): array
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $fromNumber = $from ?? config('services.twilio.from_number');

        if (! $accountSid || ! $authToken || ! $fromNumber || ! $to || ! $body) {
            Log::warning('Twilio credentials, sender, recipient or message body are missing.');

            return ['sent' => false, 'error' => 'missing_config'];
        }

        try {
            $response = Http::asForm()->withBasicAuth($accountSid, $authToken)->acceptJson()->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $to,
                    'Body' => $body,
                ]);
        } catch (\Throwable $exception) {
            Log::error('Twilio SMS request failed.', ['exception' => $exception->getMessage()]);

            return ['sent' => false, 'error' => 'provider_unavailable'];
        }

        if ($response->failed()) {
            Log::warning('Twilio rejected an SMS.', ['status' => $response->status(), 'code' => $response->json('code')]);

            return ['sent' => false, 'error' => 'provider_error'];
        }

        return ['sent' => true, 'response' => $response->json()];
    }

    public function sendToParticipants(string $title, string $body, array $data = []): int
    {
        $count = 0;
        User::whereNotNull('fcm_token')->where('role', 'participant')->orderBy('id')
            ->chunkById(100, function ($users) use ($title, $body, $data, &$count): void {
                foreach ($users as $user) {
                    $result = $this->sendToDevice($user->fcm_token, $title, $body, $data);
                    if ($result['sent']) {
                        $count++;
                    } elseif (($result['error'] ?? null) === 'unregistered') {
                        $user->forceFill(['fcm_token' => null])->save();
                    }
                }
            });

        return $count;
    }

    public function sendSmsToParticipants(string $title, string $body, ?string $from = null): int
    {
        $count = 0;
        User::whereNotNull('phone')->where('role', 'participant')->orderBy('id')
            ->chunkById(100, function ($users) use ($body, $from, &$count): void {
                foreach ($users as $user) {
                    $result = $this->sendSms($user->phone, $body, $from);
                    if ($result['sent']) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        $user = User::find($userId);
        if (! $user || ! $user->fcm_token) {
            return ['sent' => false, 'error' => 'user_or_token_not_found'];
        }

        $result = $this->sendToDevice($user->fcm_token, $title, $body, $data);
        if (($result['error'] ?? null) === 'unregistered') {
            $user->forceFill(['fcm_token' => null])->save();
        }

        return $result;
    }

    private function fcmCredentials(): ?array
    {
        $credentials = config('services.fcm.service_account_json');
        if (is_string($credentials)) {
            $credentials = json_decode($credentials, true);
        }

        return is_array($credentials)
            && ! empty($credentials['client_email'])
            && ! empty($credentials['private_key'])
            && ! empty($credentials['token_uri'])
            ? $credentials
            : null;
    }

    private function fcmAccessToken(array $credentials): ?string
    {
        $cacheKey = 'fcm.oauth.'.hash('sha256', $credentials['client_email']);

        try {
            return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): ?string {
                $issuedAt = time();
                $claims = [
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $credentials['token_uri'],
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + 3600,
                ];
                $header = ['alg' => 'RS256', 'typ' => 'JWT'];
                $unsigned = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)).'.'
                    .$this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
                $key = openssl_pkey_get_private($credentials['private_key']);
                if (! $key || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
                    Log::error('Could not sign Google service account assertion.');

                    return null;
                }

                $assertion = $unsigned.'.'.$this->base64UrlEncode($signature);
                $response = Http::asForm()->acceptJson()->timeout(15)->post($credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

                if ($response->failed() || ! is_string($response->json('access_token'))) {
                    Log::warning('Google service account token exchange failed.', ['status' => $response->status()]);

                    return null;
                }

                return $response->json('access_token');
            });
        } catch (\Throwable $exception) {
            Log::error('FCM authentication failed.', ['exception' => $exception->getMessage()]);

            return null;
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
