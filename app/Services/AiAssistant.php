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
            'ai_api_key', 'ai_api_base_url', 'ai_api_model', 'ai_provider',
        ])->pluck('value', 'key');
        $storedKey = $settings->get('ai_api_key');
        if (!$storedKey || !str_starts_with($storedKey, 'enc:')) {
            return null;
        }

        try {
            $key = Crypt::decryptString(substr($storedKey, 4));
            return $this->requestCompletion($settings->all(), $key, $system, $prompt, $maxTokens)['content'];
        } catch (Throwable) {
            return null;
        }
    }

    public function testConnection(array $settings): array
    {
        try {
            $key = trim((string) ($settings['ai_api_key'] ?? ''));
            if ($key === '') {
                $saved = DB::table('settings')->pluck('value', 'key');
                if (($saved->get('ai_provider', 'openai-compatible') !== $settings['ai_provider']) ||
                    rtrim($saved->get('ai_api_base_url', ''), '/') !== rtrim($settings['ai_api_base_url'], '/')) {
                    return ['ok'=>false, 'message'=>'Enter a new API key when testing a different provider or URL.'];
                }
                $stored = $saved->get('ai_api_key');
                if (!$stored || !str_starts_with($stored, 'enc:')) {
                    return ['ok'=>false, 'message'=>'Enter an API key to test the connection.'];
                }
                $key = Crypt::decryptString(substr($stored, 4));
            }
            $result = $this->requestCompletion($settings, $key, 'You are a connection test assistant.', 'Reply with OK.', 512);
            if ($result['content'] !== null) {
                return ['ok'=>true, 'message'=>'Connection successful. The selected model responded.'];
            }
            return ['ok'=>false, 'message'=>$result['status'] >= 400
                ? 'Provider returned HTTP '.$result['status'].'. Check the API key, model, URL and account quota.'
                : 'The provider did not return a usable response. Check the model and API URL.'];
        } catch (Throwable) {
            return ['ok'=>false, 'message'=>'Could not connect to the provider. Check the API URL and server connectivity.'];
        }
    }

    private function requestCompletion(array $settings, string $key, string $system, string $prompt, int $maxTokens): array
    {
        $provider = config('ai.providers.'.($settings['ai_provider'] ?? 'openai-compatible'), []);
        $format = $provider['format'] ?? 'openai';
        $baseUrl = rtrim($settings['ai_api_base_url'] ?? $provider['url'] ?? 'https://api.openai.com/v1', '/');
        $model = $settings['ai_api_model'] ?? $provider['models'][0] ?? 'gpt-4o-mini';
        $http = Http::timeout(20)->connectTimeout(10)->withoutRedirecting()->acceptJson();
        $messages = [['role'=>'system', 'content'=>$system], ['role'=>'user', 'content'=>$prompt]];

        if ($format === 'anthropic') {
            $response = $http->withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])
                ->post($baseUrl.'/messages', ['model'=>$model, 'system'=>$system, 'messages'=>[['role'=>'user','content'=>$prompt]], 'max_tokens'=>$maxTokens]);
            $content = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode("\n");
        } elseif ($format === 'gemini') {
            $response = $http->withHeaders(['x-goog-api-key'=>$key])->post($baseUrl.'/models/'.rawurlencode($model).':generateContent', [
                'systemInstruction'=>['parts'=>[['text'=>$system]]],
                'contents'=>[['role'=>'user', 'parts'=>[['text'=>$prompt]]]],
                'generationConfig'=>['maxOutputTokens'=>$maxTokens],
            ]);
            $content = collect($response->json('candidates.0.content.parts', []))->pluck('text')->implode("\n");
        } elseif ($format === 'cohere') {
            $response = $http->withToken($key)->post($baseUrl.'/chat', ['model'=>$model, 'messages'=>$messages, 'max_tokens'=>$maxTokens]);
            $content = collect($response->json('message.content', []))->where('type', 'text')->pluck('text')->implode("\n");
        } else {
            $tokenParameter = ($settings['ai_provider'] ?? '') === 'openai' && preg_match('/^(gpt-5|o[134])/', $model)
                ? 'max_completion_tokens' : 'max_tokens';
            $response = $http->withToken($key)->post($baseUrl.'/chat/completions', [
                'model'=>$model, 'messages'=>$messages, $tokenParameter=>$maxTokens,
            ]);
            $content = $response->json('choices.0.message.content');
        }

        return ['status'=>$response->status(), 'content'=>$response->successful() && is_string($content) && trim($content) !== '' ? trim($content) : null];
    }

    public function configured(): bool
    {
        $stored = DB::table('settings')->where('key', 'ai_api_key')->value('value');
        return is_string($stored) && str_starts_with($stored, 'enc:');
    }
}
