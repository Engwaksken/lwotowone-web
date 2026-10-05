<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        $projectId = config('services.fcm.project_id');
        $serverKey = config('services.fcm.server_key');

        if (empty($token) || empty($serverKey)) {
            Log::warning('FCM token or server key missing, cannot send push notification');
            return ['sent' => false, 'error' => 'missing_config'];
        }

        $response = Http::withHeaders([
            'Authorization' => "key=$serverKey",
            'Content-Type' => 'application/json',
        ]->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge([
                    'title' => $title,
                    'body' => $body,
                ], $data),
            ],
        ]);

        if ($response->failed()) {
            Log::error('FCM send failed', [
                'token' => substr($token, 0, 20) . '...',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['sent' => false, 'error' => $response->body()];
        }

        $result = $response->json();
        return ['sent' => true, 'response' => $result];
    }

    public function sendSms(string $to, string $body, string $from = null): array
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $fromNumber = $from ?? config('services.twilio.from_number');

        if (empty($accountSid) || empty($authToken) || empty($fromNumber)) {
            Log::warning('Twilio config missing, cannot send SMS');
            return ['sent' => false, 'error' => 'missing_config'];
        }

        $response = Http::basicAuth($accountSid, $authToken)->post('https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json', [
            'From' => $fromNumber,
            'To' => $to,
            'Body' => $body,
        ]);

        if ($response->failed()) {
            Log::error('SMS send failed', [
                'to' => substr($to, 0, 20) . '...',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['sent' => false, 'error' => $response->body()];
        }

        $result = $response->json();
        return ['sent' => true, 'response' => $result];
    }

    public function sendToParticipants(string $title, string $body, array $data = []): int
    {
        $tokens = \App\Models\User::whereNotNull('fcm_token')
            ->where('role', 'participant')
            ->pluck('fcm_token')
            ->toArray();

        $count = 0;
        foreach ($tokens as $token) {
            $result = $this->sendToDevice($token, $title, $body, $data);
            if ($result['sent']) {
                $count++;
            }
        }

        return $count;
    }

    public function sendSmsToParticipants(string $title, string $body, string $from = null): int
    {
        $users = \App\Models\User::whereNotNull('phone')
            ->where('role', 'participant')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $result = $this->sendSms($user->phone, $body, $from);
            if ($result['sent']) {
                $count++;
            }
        }

        return $count;
    }

    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        $user = \App\Models\User::find($userId);
        if (!$user || !$user->fcm_token) {
            return ['sent' => false, 'error' => 'user_or_token_not_found'];
        }

        return $this->sendToDevice($user->fcm_token, $title, $body, $data);
    }
}