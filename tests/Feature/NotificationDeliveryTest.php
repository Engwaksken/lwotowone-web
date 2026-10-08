<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function configureFcm(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        config(['services.fcm' => [
            'project_id' => null,
            'service_account_json' => json_encode([
                'project_id' => 'training-project',
                'client_email' => 'firebase@example.test',
                'private_key' => $privateKey,
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ], JSON_THROW_ON_ERROR),
        ]]);
        Cache::flush();
    }

    private function participant(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Learner',
            'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password',
            'role' => 'participant',
            'status' => 'active',
        ], $attributes));
    }

    public function test_fcm_v1_uses_a_cached_service_account_token_and_sends_a_message(): void
    {
        $this->configureFcm();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/v1/projects/training-project/messages:send' => Http::response(['name' => 'projects/training-project/messages/1']),
        ]);

        $service = app(NotificationService::class);
        $first = $service->sendToDevice('device-token', 'Workshop', 'Starts tomorrow', ['screen' => 'events', 'position' => 3]);
        $second = $service->sendToDevice('another-device', 'Workshop', 'Starts tomorrow');

        $this->assertTrue($first['sent']);
        $this->assertTrue($second['sent']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $request['assertion']) === 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request->hasHeader('Authorization', 'Bearer google-access-token')
            && $request['message']['token'] === 'device-token'
            && $request['message']['data']['position'] === '3');
        Http::assertSentCount(3);
    }

    public function test_fcm_unregistered_device_tokens_are_removed_after_provider_response(): void
    {
        $this->configureFcm();
        $user = $this->participant(['fcm_token' => 'expired-token']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'fcm.googleapis.com/*' => Http::response([
                'error' => ['details' => [['errorCode' => 'UNREGISTERED']]],
            ], 404),
        ]);

        $sent = app(NotificationService::class)->sendToParticipants('Update', 'New content');

        $this->assertSame(0, $sent);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'fcm_token' => null]);
    }

    public function test_twilio_uses_configured_credentials_and_form_encoded_message(): void
    {
        config(['services.twilio' => [
            'account_sid' => 'AC123',
            'auth_token' => 'twilio-secret',
            'from_number' => '+256700000000',
        ]]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);

        $result = app(NotificationService::class)->sendSms('+256711111111', 'Your session is confirmed.');

        $this->assertTrue($result['sent']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && str_contains(implode(';', $request->header('Content-Type')), 'application/x-www-form-urlencoded')
            && $request['From'] === '+256700000000'
            && $request['To'] === '+256711111111'
            && $request['Body'] === 'Your session is confirmed.');
    }

    public function test_missing_provider_configuration_returns_a_safe_error_without_http_requests(): void
    {
        Http::preventStrayRequests();

        $push = app(NotificationService::class)->sendToDevice('device-token', 'Title', 'Body');
        $sms = app(NotificationService::class)->sendSms('+256711111111', 'Body');

        $this->assertSame('missing_config', $push['error']);
        $this->assertSame('missing_config', $sms['error']);
    }

    public function test_participants_can_register_or_remove_only_their_own_push_token(): void
    {
        $participant = $this->participant();
        $token = $participant->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/device-token', ['token' => 'device-token-123'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $participant->id, 'fcm_token' => 'device-token-123']);
        $this->withToken($token)->deleteJson('/api/device-token')->assertOk();
        $this->assertDatabaseHas('users', ['id' => $participant->id, 'fcm_token' => null]);
    }

    public function test_staff_cannot_register_a_participant_push_token(): void
    {
        $manager = User::create([
            'name' => 'Manager',
            'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password',
            'role' => 'manager',
            'status' => 'active',
        ]);
        $managerToken = $manager->createToken('test')->plainTextToken;

        $this->withToken($managerToken)->postJson('/api/device-token', ['token' => 'not-allowed'])->assertForbidden();
    }
}
