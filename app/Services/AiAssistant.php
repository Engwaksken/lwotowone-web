<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiAssistant
{
    public function complete(string $system, string $prompt, int $maxTokens = 500): ?string
    {
        $settings = DB::table('settings')->whereIn('key', [
            'ai_api_key', 'ai_api_base_url', 'ai_api_model',
        ])->pluck('value', 'key');
        $storedKey = $settings->get('ai_api_key');
        if (!$storedKey || !str_starts_with($storedKey, 'enc:')) {
            return null;
        }

        try {
            $key = Crypt::decryptString(substr($storedKey, 4));
            $baseUrl = rtrim($settings->get('ai_api_base_url', 'https://api.openai.com/v1'), '/');
            $response = Http::timeout(20)
                ->withToken($key)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $settings->get('ai_api_model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.25,
                    'max_tokens' => $maxTokens,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $content = $response->json('choices.0.message.content');
            return is_string($content) && trim($content) !== '' ? trim($content) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function configured(): bool
    {
        $stored = DB::table('settings')->where('key', 'ai_api_key')->value('value');
        return is_string($stored) && str_starts_with($stored, 'enc:');
    }
}
