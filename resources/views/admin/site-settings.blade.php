@extends('layout')
@section('title',auth()->user()->role==='admin'?'Website appearance settings':'Payment settings')
@section('content')
<div class="settings-page">
    <span class="eyebrow">{{ auth()->user()->role==='admin'?'Your public identity':'Learner payment options' }}</span>
    <h1>{{ auth()->user()->role==='admin'?'Website appearance':'Payment settings' }}</h1>
    <p class="settings-intro">Manage the platform’s visual identity and payment options in one place.</p>
    <div class="tabs settings-tabs" data-tabs><div class="tab-list" role="tablist" aria-label="Site settings">
        @if(auth()->user()->role==='admin')<button type="button" role="tab" id="settings-tab-appearance" aria-controls="settings-panel-appearance" aria-selected="true">Website appearance</button>@endif
        <button type="button" role="tab" id="settings-tab-payments" aria-controls="settings-panel-payments" aria-selected="{{ auth()->user()->role==='admin'?'false':'true' }}" @if(auth()->user()->role==='admin')tabindex="-1"@endif>Payment gateway settings</button>
        @if(auth()->user()->role==='admin')<button type="button" role="tab" id="settings-tab-ai" aria-controls="settings-panel-ai" aria-selected="false" tabindex="-1">AI API settings</button>@endif
    </div>
    @if(auth()->user()->role==='admin')
    <section class="tab-panel" role="tabpanel" id="settings-panel-appearance" aria-labelledby="settings-tab-appearance">
    <form class="panel settings-form" method="post" action="/admin/site-settings" enctype="multipart/form-data">
        @csrf @method('PUT')
        <section class="settings-group"><h2>Colours</h2><p>Choose accessible brand colours for buttons, links and highlights.</p>
            <div class="settings-fields">
                <div class="field"><label for="primary_color">Primary brand colour</label><input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color',$settings['primary_color']??'#175742') }}" required><small>Used for primary actions and links.</small></div>
                <div class="field"><label for="accent_color">Accent colour</label><input id="accent_color" type="color" name="accent_color" value="{{ old('accent_color',$settings['accent_color']??'#dfb452') }}" required><small>Used for focus rings and supporting details.</small></div>
            </div>
        </section>
        <section class="settings-group"><h2>Typography</h2><p>Choose a clear typeface and comfortable base text size.</p>
            <div class="settings-fields">
                <div class="field"><label for="font_family">Font family</label><select id="font_family" name="font_family" required>@foreach(['Arial'=>'Arial','system'=>'System sans-serif','Georgia'=>'Georgia (serif)','Atkinson Hyperlegible'=>'Atkinson Hyperlegible'] as $value=>$label)<option value="{{ $value }}" @selected(old('font_family',$settings['font_family']??'Arial')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="font_size">Base text size</label><select id="font_size" name="font_size" required>@foreach([14,15,16,17,18,19,20] as $size)<option value="{{ $size }}" @selected((int)old('font_size',$settings['font_size']??16)===$size)>{{ $size }} px</option>@endforeach</select></div>
            </div>
        </section>
        <section class="settings-group"><h2>Logo and favicon</h2><p>Upload a clear logo and a square browser icon. Logos accept PNG, JPG or WebP (up to 4 MB); favicons accept PNG or ICO (up to 1 MB).</p>
            <div class="settings-fields">
                <div class="field"><label for="logo">Site logo <span class="optional">(optional)</span></label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">@if(!empty($settings['site_logo']))<img class="settings-preview-logo" src="{{ $settings['site_logo'] }}" alt="Current site logo">@endif</div>
                <div class="field"><label for="favicon">Favicon <span class="optional">(optional)</span></label><input id="favicon" type="file" name="favicon" accept="image/png,image/x-icon">@if(!empty($settings['site_favicon']))<img class="settings-preview-icon" src="{{ $settings['site_favicon'] }}" alt="Current site favicon">@endif</div>
            </div>
        </section>
        <div class="settings-save"><button type="submit"><i class="fas fa-check" aria-hidden="true"></i> Save appearance</button></div>
    </form>
    </section>
    @endif
    <section class="tab-panel" role="tabpanel" id="settings-panel-payments" aria-labelledby="settings-tab-payments" @if(auth()->user()->role==='admin')hidden @endif><section class="panel payment-settings" id="payment-methods"><span class="eyebrow">Learner payment options</span><h2>Payment gateways and methods</h2><p>Add verified payment destinations and clear learner instructions.</p>
        <form method="post" action="/admin/site-settings/payment-gateways" class="grid">@csrf
            <div class="field"><label for="gateway-name">Display name</label><input id="gateway-name" name="name" required></div>
            <div class="field"><label for="gateway-type">Payment type</label><select id="gateway-type" name="provider_type" required>@foreach(['IOTEC','Bank','Mobile Money','Merchant Code','Other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
            <div class="field"><label for="gateway-provider">Provider or bank</label><input id="gateway-provider" name="provider"></div>
            <div class="field"><label for="gateway-account-name">Account holder</label><input id="gateway-account-name" name="account_name"></div>
            <div class="field"><label for="gateway-account-number">Account or phone number</label><input id="gateway-account-number" name="account_number"></div>
            <div class="field"><label for="gateway-merchant-code">Merchant code</label><input id="gateway-merchant-code" name="merchant_code"></div>
            <div class="field"><label for="gateway-currency">Currency</label><select id="gateway-currency" name="currency" required>@foreach(['UGX','USD','EUR','GBP','KES'] as $currency)<option value="{{ $currency }}">{{ $currency }}</option>@endforeach</select></div>
            <div class="field"><label for="gateway-amount">Amount <span class="optional">(optional)</span></label><input id="gateway-amount" type="number" min="0" step="0.01" name="amount"></div>
            <div class="field"><label for="gateway-instructions">Payment instructions</label><textarea id="gateway-instructions" name="instructions"></textarea></div>
            <div class="field"><label><input type="checkbox" name="active" value="1" checked> Show to learners</label></div><div class="field"><button type="submit">Add payment option</button></div>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Provider</th><th>Account or merchant</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($gateways as $gateway)<tr><td>{{ $gateway->name }}</td><td>{{ $gateway->provider_type }}</td><td>{{ $gateway->provider }}</td><td>{{ $gateway->account_number?:$gateway->merchant_code }}</td><td>{{ $gateway->amount?number_format($gateway->amount,2).' '.$gateway->currency:'—' }}</td><td>{{ $gateway->active?'Active':'Hidden' }}</td><td><form method="post" action="/admin/site-settings/payment-gateways/{{ $gateway->id }}" data-confirm="Delete this payment option?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete payment option" aria-label="Delete payment option"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form></td></tr>@empty<tr><td colspan="7">No payment destinations configured.</td></tr>@endforelse
        </tbody></table></div>
    </section></section>
    @if(auth()->user()->role==='admin')
    <section class="tab-panel" role="tabpanel" id="settings-panel-ai" aria-labelledby="settings-tab-ai" hidden>
        <form class="panel settings-form ai-settings-form" data-ai-settings method="post" action="/admin/site-settings/ai">@csrf @method('PUT')
            <span class="eyebrow">Chatbot and mentor matching</span><h2>AI API connection</h2>
            <p>Select a provider to fill in its API URL and model choices, then add your API key. You can also enter a custom model or HTTPS endpoint. The key is encrypted at rest and never shown after saving. Chat messages and learning goals may be sent to this provider for responses and recommendations.</p>
            @php
                $aiProviders=config('ai.providers');
                $selectedProvider=old('ai_provider',$settings['ai_provider']??'openai');
                $providerPreset=$aiProviders[$selectedProvider]??$aiProviders['openai'];
                $selectedModel=old('ai_api_model',$settings['ai_api_model']??$providerPreset['models'][0]);
            @endphp
            <div class="settings-fields">
                <div class="field"><label for="ai-provider">API provider</label><select id="ai-provider" name="ai_provider" required>@foreach($aiProviders as $id=>$provider)<option value="{{ $id }}" @selected($selectedProvider===$id)>{{ $provider['label'] }}</option>@endforeach</select></div>
                <div class="field"><label for="ai-model-choice">Model</label><select id="ai-model-choice">@foreach($providerPreset['models'] as $model)<option value="{{ $model }}" @selected($selectedModel===$model)>{{ $model }}</option>@endforeach<option value="__custom" @selected(!in_array($selectedModel,$providerPreset['models'],true))>Custom model ID</option></select><label for="ai-model" class="custom-model-label">Model ID</label><input id="ai-model" name="ai_api_model" value="{{ $selectedModel }}" maxlength="120" placeholder="Enter model ID" required><small>Model availability depends on your provider account. Choose Custom model ID for other models.</small></div>
                <div class="field"><label for="ai-base-url">API base URL</label><input id="ai-base-url" type="url" name="ai_api_base_url" list="ai-base-urls" value="{{ old('ai_api_base_url',$settings['ai_api_base_url']??$providerPreset['url']) }}" placeholder="https://api.openai.com/v1" maxlength="500" required><datalist id="ai-base-urls">@foreach($aiProviders as $provider)<option value="{{ $provider['url'] }}">{{ $provider['label'] }}</option>@endforeach</datalist><small>Filled automatically for the provider; editable for a trusted HTTPS endpoint.</small></div>
                <div class="field"><label for="ai-api-key">API key {{ $aiConfigured?'(configured; leave blank to keep it)':' ' }}</label><input id="ai-api-key" type="password" name="ai_api_key" data-secret-label="API key" autocomplete="new-password" maxlength="4096" placeholder="{{ $aiConfigured?'Saved securely — enter a new key to replace it':'Enter API key' }}"><small>{{ $aiConfigured?'Leave blank to test or retain the saved key for the same provider and URL.':'The chatbot uses built-in answers until a key is configured.' }}</small></div>
            </div>
            @if($aiConfigured)<div class="field"><label><input type="checkbox" name="clear_api_key" value="1"> Remove saved API key and use built-in chat answers</label></div>@endif
            <div class="settings-save ai-settings-actions"><button class="secondary" type="button" data-ai-test><i class="fas fa-plug" aria-hidden="true"></i> Test connection</button><button type="submit"><i class="fas fa-lock" aria-hidden="true"></i> Save AI settings</button></div>
            <p class="ai-test-result" data-ai-test-result role="status" aria-live="polite"></p>
            <small>Testing sends a short request to the selected model and may use a small amount of your provider quota. It does not save changes.</small>
        </form>
        <script type="application/json" id="ai-provider-presets">{!! json_encode($aiProviders, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    </section>
    @endif
    </div>
</div>
@endsection
