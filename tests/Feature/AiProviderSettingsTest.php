<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB, Http};
use Tests\TestCase;

class AiProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'admin'): User
    {
        return User::create(['name'=>'Test user', 'email'=>uniqid().'@example.test', 'password'=>'A-long-test-password', 'role'=>$role, 'status'=>'active']);
    }

    private function settings(string $provider = 'openai'): array
    {
        $preset = config('ai.providers.'.$provider);
        return ['ai_provider'=>$provider, 'ai_api_base_url'=>$preset['url'], 'ai_api_model'=>$preset['models'][0], 'ai_api_key'=>'test-provider-secret'];
    }

    public function test_provider_settings_render_and_topbar_orders_dashboard_profile_logout(): void
    {
        $html = $this->actingAs($this->account())->get('/admin/site-settings')->assertOk()
            ->assertSee('Anthropic (Claude)')->assertSee('Google Gemini')->assertSee('Custom model ID')->assertSee('Test connection')->getContent();
        preg_match('~<header>(.*?)</header>~s', $html, $header);
        $this->assertLessThan(strpos($header[1], 'href="/profile"'), strpos($header[1], 'href="/dashboard"'));
        $this->assertLessThan(strpos($header[1], 'action="/logout"'), strpos($header[1], 'href="/profile"'));
    }

    public function test_connection_uses_unsaved_settings_and_never_saves_or_exposes_key(): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['choices'=>[['message'=>['content'=>'OK']]]])]);
        $response = $this->actingAs($this->account())->postJson('/admin/site-settings/ai/test', $this->settings())
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertStringNotContainsString('test-provider-secret', $response->getContent());
        $this->assertDatabaseCount('settings', 0);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-provider-secret') && $request['model']==='gpt-4o-mini');
    }

    public function test_saved_key_can_be_tested_but_cannot_be_sent_to_a_different_provider(): void
    {
        $this->actingAs($this->account())->put('/admin/site-settings/ai', $this->settings())->assertRedirect()->assertSessionHasNoErrors();
        Http::fake(['api.openai.com/*'=>Http::response(['choices'=>[['message'=>['content'=>'OK']]]])]);
        $data = $this->settings();
        $data['ai_api_key'] = '';
        $this->postJson('/admin/site-settings/ai/test', $data)->assertOk()->assertJsonPath('ok', true);
        Http::assertSentCount(1);
        $data['ai_api_base_url'] = 'https://different.example.test/v1';
        $this->postJson('/admin/site-settings/ai/test', $data)->assertOk()->assertJsonPath('ok', false);
        Http::assertSentCount(1);
        $this->put('/admin/site-settings/ai', $data)->assertSessionHasErrors('ai_api_key');
        $this->assertDatabaseHas('settings', ['key'=>'ai_api_base_url', 'value'=>'https://api.openai.com/v1']);
    }

    public function test_native_providers_use_their_own_authentication_payloads_and_response_formats(): void
    {
        Http::fake([
            'api.anthropic.com/*'=>Http::response(['content'=>[['type'=>'text', 'text'=>'Claude reply']]]),
            'generativelanguage.googleapis.com/*'=>Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>'Gemini reply']]]]]]),
            'api.cohere.com/*'=>Http::response(['message'=>['content'=>[['type'=>'text', 'text'=>'Cohere reply']]]]),
        ]);
        $this->actingAs($this->account());
        foreach (['anthropic'=>'Claude reply', 'gemini'=>'Gemini reply', 'cohere'=>'Cohere reply'] as $provider=>$reply) {
            $this->put('/admin/site-settings/ai', $this->settings($provider))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($reply, app(AiAssistant::class)->complete('System instruction', 'Question'));
        }
        Http::assertSent(fn ($request) => $request->url()==='https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-provider-secret') && $request['system']==='System instruction');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/models/gemini-2.5-flash:generateContent')
            && $request->hasHeader('x-goog-api-key', 'test-provider-secret') && $request['contents'][0]['parts'][0]['text']==='Question');
        Http::assertSent(fn ($request) => $request->url()==='https://api.cohere.com/v2/chat'
            && $request->hasHeader('Authorization', 'Bearer test-provider-secret') && $request['messages'][0]['role']==='system');
        $stored = DB::table('settings')->where('key', 'ai_api_key')->value('value');
        $this->assertSame('test-provider-secret', Crypt::decryptString(substr($stored, 4)));
    }

    public function test_connection_failures_are_sanitized_and_invalid_settings_do_not_send_requests(): void
    {
        Http::fake(['*'=>Http::response(['error'=>['message'=>'Sensitive test-provider-secret']], 401)]);
        $this->actingAs($this->account());
        $response = $this->postJson('/admin/site-settings/ai/test', $this->settings())->assertOk()->assertJsonPath('ok', false);
        $this->assertStringContainsString('HTTP 401', $response->json('message'));
        $this->assertStringNotContainsString('test-provider-secret', $response->getContent());
        $data = $this->settings(); $data['ai_provider'] = 'unknown';
        $this->postJson('/admin/site-settings/ai/test', $data)->assertUnprocessable()->assertJsonValidationErrors('ai_provider');
        $data = $this->settings(); $data['ai_api_base_url'] = 'http://example.test';
        $this->postJson('/admin/site-settings/ai/test', $data)->assertUnprocessable()->assertJsonValidationErrors('ai_api_base_url');
        Http::assertSentCount(1);
    }

    public function test_only_admins_can_save_or_test_ai_settings_and_keys_are_not_flashed(): void
    {
        Http::fake();
        $this->actingAs($this->account('manager'))->postJson('/admin/site-settings/ai/test', $this->settings())->assertForbidden();
        $this->put('/admin/site-settings/ai', $this->settings())->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($this->account());
        $data = $this->settings(); $data['ai_api_model'] = '';
        $this->put('/admin/site-settings/ai', $data)->assertSessionHasErrors('ai_api_model');
        $this->assertNull(session()->getOldInput('ai_api_key'));
    }

    public function test_openai_reasoning_models_use_completion_token_parameter(): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['choices'=>[['message'=>['content'=>'OK']]]])]);
        $data = $this->settings(); $data['ai_api_model'] = 'gpt-5-mini';
        $this->actingAs($this->account())->postJson('/admin/site-settings/ai/test', $data)->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(fn ($request) => $request['max_completion_tokens']===512 && !isset($request['max_tokens']));
    }
}
