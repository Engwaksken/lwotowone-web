<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function participant(): User
    {
        return User::create([
            'name' => 'Learner',
            'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password',
            'role' => 'participant',
            'status' => 'active',
        ]);
    }

    private function enterprise(User $user): int
    {
        return DB::table('enterprises')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Farm',
            'sector' => 'Agriculture',
            'idea' => 'Poultry',
            'stage' => 'idea',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_intent_uses_provider_minor_units_and_returns_client_secret(): void
    {
        config(['services.stripe.secret' => 'sk_test_example']);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'pi_example', 'client_secret' => 'pi_example_secret',
        ])]);

        $result = app(PaymentService::class)->createPaymentIntent(12.34, 'usd', 15, 'income', 'Produce');

        $this->assertSame(['success' => true, 'client_secret' => 'pi_example_secret', 'id' => 'pi_example'], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
            && $request['amount'] == 1234 && $request['currency'] === 'usd'
            && $request['metadata[enterprise_id]'] === '15');
    }

    public function test_signed_success_webhook_records_owned_payment_once(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_example']);
        $user = $this->participant();
        $enterprise = $this->enterprise($user);
        $body = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_success', 'amount' => 125000, 'created' => 1791331200,
                'metadata' => ['enterprise_id' => (string) $enterprise, 'type' => 'income'],
            ]],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');
        $header = "t={$timestamp},v1={$signature}";

        $service = app(PaymentService::class);
        $this->assertTrue($service->handleWebhook($header, $body)['success']);
        $this->assertTrue($service->handleWebhook($header, $body)['success']);

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'enterprise_id' => $enterprise,
            'type' => 'income',
            'amount' => '1250.00',
            'description' => 'Stripe payment pi_success',
        ]);
    }

    public function test_unsigned_and_stale_webhooks_are_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_example']);
        $body = '{"type":"ignored.event"}';
        $service = app(PaymentService::class);

        $this->assertSame('invalid_signature', $service->handleWebhook('', $body)['error']);
        $timestamp = time() - 301;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');
        $this->assertSame('invalid_signature', $service->handleWebhook("t={$timestamp},v1={$signature}", $body)['error']);
    }

    public function test_zero_decimal_currency_webhooks_keep_the_whole_amount(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_example']);
        $user = $this->participant();
        $enterprise = $this->enterprise($user);
        $body = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_ugx', 'amount_received' => 125000, 'currency' => 'ugx',
                'metadata' => ['enterprise_id' => (string) $enterprise, 'type' => 'income'],
            ]],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');

        $this->assertTrue(app(PaymentService::class)->handleWebhook("t={$timestamp},v1={$signature}", $body)['success']);
        $this->assertDatabaseHas('transactions', ['description' => 'Stripe payment pi_ugx', 'amount' => '125000.00']);
    }

    public function test_payment_routes_are_participant_scoped(): void
    {
        $owner = $this->participant();
        $enterprise = $this->enterprise($owner);
        $other = $this->participant();
        config(['services.stripe.secret' => 'sk_test_example']);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'pi_example', 'client_secret' => 'pi_example_secret',
        ])]);

        $this->actingAs($other)->postJson('/api/payment-intent', [
            'amount' => 10, 'currency' => 'UGX', 'enterprise_id' => $enterprise, 'type' => 'income',
        ])->assertForbidden();

        $this->actingAs($other)->getJson('/api/transactions?enterprise_id='.$enterprise)->assertForbidden();
        $this->actingAs($owner)->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'transactions');
    }
}
