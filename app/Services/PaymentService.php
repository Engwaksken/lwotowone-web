<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function createPaymentIntent(float $amount, string $currency, int|string $enterpriseId, string $type, string $description = ''): array
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            Log::warning('Stripe is not configured; payment intent was not created.');

            return ['success' => false, 'error' => 'missing_config'];
        }

        try {
            $response = Http::asForm()->withBasicAuth($secret, '')
                ->acceptJson()->timeout(15)
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => in_array(strtolower($currency), ['ugx', 'jpy', 'krw'], true)
                        ? (int) round($amount)
                        : (int) round($amount * 100),
                    'currency' => strtolower($currency),
                    'metadata[enterprise_id]' => (string) $enterpriseId,
                    'metadata[type]' => $type,
                    'description' => $description,
                ]);
        } catch (\Throwable $exception) {
            Log::error('Stripe payment intent request failed.', ['exception' => $exception->getMessage()]);

            return ['success' => false, 'error' => 'provider_unavailable'];
        }

        if (! $response->successful() || ! $response->json('id') || ! $response->json('client_secret')) {
            Log::warning('Stripe rejected payment intent creation.', ['status' => $response->status()]);

            return ['success' => false, 'error' => 'provider_error'];
        }

        return [
            'success' => true,
            'client_secret' => $response->json('client_secret'),
            'id' => $response->json('id'),
        ];
    }

    /** Verify and process a Stripe webhook using the endpoint signing secret. */
    public function handleWebhook(string $signatureHeader, string $body): array
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return ['success' => false, 'error' => 'missing_config'];
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && ctype_digit((string) $value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > 300 || $signatures === []) {
            return ['success' => false, 'error' => 'invalid_signature'];
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $valid = false;
        foreach ($signatures as $signature) {
            $valid = hash_equals($expected, $signature) || $valid;
        }
        if (! $valid) {
            return ['success' => false, 'error' => 'invalid_signature'];
        }

        $event = json_decode($body, true);
        if (! is_array($event) || ! is_string($event['type'] ?? null)) {
            return ['success' => false, 'error' => 'invalid_payload'];
        }

        if ($event['type'] === 'payment_intent.succeeded') {
            $intent = $event['data']['object'] ?? [];
            if (! is_array($intent) || ! $this->recordSucceededPayment($intent)) {
                return ['success' => false, 'error' => 'invalid_payment'];
            }
        }

        return ['success' => true, 'event_type' => $event['type']];
    }

    private function recordSucceededPayment(array $intent): bool
    {
        $intentId = $intent['id'] ?? null;
        $enterpriseId = filter_var($intent['metadata']['enterprise_id'] ?? null, FILTER_VALIDATE_INT);
        $ownerId = DB::table('enterprises')->where('id', $enterpriseId)->value('user_id');
        $amount = filter_var($intent['amount_received'] ?? $intent['amount'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtolower((string) ($intent['currency'] ?? 'usd'));
        $divisor = in_array($currency, ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'], true) ? 1 : 100;
        $type = $intent['metadata']['type'] ?? null;

        if (! is_string($intentId) || ! str_starts_with($intentId, 'pi_') || ! $ownerId
            || $amount === false || $amount <= 0 || ! in_array($type, ['income', 'expense'], true)) {
            return false;
        }

        $description = 'Stripe payment '.$intentId;
        DB::transaction(function () use ($intentId, $enterpriseId, $ownerId, $amount, $divisor, $type, $description, $intent): void {
            // Serialize receipt handling for the enterprise so webhook retries cannot
            // race into duplicate transaction rows on databases with row locking.
            DB::table('enterprises')->where('id', $enterpriseId)->lockForUpdate()->first();
            $existing = DB::table('transactions')->where('user_id', $ownerId)
                ->where('enterprise_id', $enterpriseId)->where('description', $description)->exists();
            if ($existing) {
                return;
            }

            $created = filter_var($intent['created'] ?? null, FILTER_VALIDATE_INT);
            DB::table('transactions')->insert([
                'user_id' => $ownerId,
                'enterprise_id' => $enterpriseId,
                'type' => $type,
                'amount' => number_format($amount / $divisor, 2, '.', ''),
                'description' => $description,
                'occurred_on' => $created ? gmdate('Y-m-d', $created) : now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return true;
    }

    public function listTransactions(int $userId, int|string|null $enterpriseId = null, ?string $type = null): \Illuminate\Support\Collection
    {
        $query = DB::table('transactions')->where('user_id', $userId);
        if ($enterpriseId !== null) {
            $query->where('enterprise_id', $enterpriseId);
        }
        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderByDesc('occurred_on')->orderByDesc('id')->get();
    }
}
