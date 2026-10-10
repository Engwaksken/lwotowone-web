@extends('layout')
@section('title',auth()->user()->role==='admin'?'Website appearance settings':'Payment settings')
@section('content')
<div class="page-wrap">
    @php
        $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
        $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
        $attributesOf = fn ($row) => is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
        $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
            ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
            ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
            ->values()->all();
        // Account numbers are masked everywhere on this page; only the last four digits show.
        $maskAccount = fn ($value) => $value === null || $value === '' ? null : (strlen((string) $value) > 4 ? str_repeat('•', strlen((string) $value) - 4).substr((string) $value, -4) : '••••');
        // Gateway rows: configuration JSON decoded for display; encrypted credentials never shown.
        $gatewayDetails = fn ($gateway) => $moreDetails(array_merge($attributesOf($gateway), ['configuration' => json_decode((string) ($gateway->configuration ?? ''), true) ?? ($gateway->configuration ?? null)]), ['name','provider_type','provider','account_number','merchant_code','amount','currency','active','credentials']);
    @endphp
    <span class="eyebrow">{{ auth()->user()->role==='admin'?'Your public identity':'Learner payment options' }}</span>
    <h1 class="page-title">{{ auth()->user()->role==='admin'?'Website appearance':'Payment settings' }}</h1>
    <p class="page-intro">Manage how the site looks and how learners pay.</p>
    <div class="tabs settings-tabs" data-tabs><div class="tab-list" role="tablist" aria-label="Site settings">
        @if(auth()->user()->role==='admin')<button type="button" role="tab" id="settings-tab-appearance" aria-controls="settings-panel-appearance" aria-selected="true">Website appearance</button>@endif
        <button type="button" role="tab" id="settings-tab-payments" aria-controls="settings-panel-payments" aria-selected="{{ auth()->user()->role==='admin'?'false':'true' }}" @if(auth()->user()->role==='admin')tabindex="-1"@endif>Payment gateway settings</button>
        @if(auth()->user()->role==='admin')<button type="button" role="tab" id="settings-tab-ai" aria-controls="settings-panel-ai" aria-selected="false" tabindex="-1">AI API settings</button>@endif
    </div>
    @if(auth()->user()->role==='admin')
    <section class="tab-panel" role="tabpanel" id="settings-panel-appearance" aria-labelledby="settings-tab-appearance">
    <form class="panel settings-form" method="post" action="/admin/site-settings" enctype="multipart/form-data">
        @csrf @method('PUT')
        <section class="settings-group"><h2>Colours</h2><p>Pick accessible colours for buttons, links and highlights.</p>
            <div class="settings-fields">
                <div class="field"><label for="primary_color">Primary brand colour</label><input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color',$settings['primary_color']??'#175742') }}" required><small>Used for primary actions and links.</small></div>
                <div class="field"><label for="accent_color">Accent colour</label><input id="accent_color" type="color" name="accent_color" value="{{ old('accent_color',$settings['accent_color']??'#dfb452') }}" required><small>Used for focus rings and supporting details.</small></div>
            </div>
        </section>
        <section class="settings-group"><h2>Typography</h2><p>Choose a clear typeface and comfortable base text size.</p>
            <div class="settings-fields">
                <div class="field"><label for="font_family">Font family</label><input id="font_family" name="font_family" list="font-families" value="{{ old('font_family',$settings['font_family']??'Arial') }}" maxlength="120" required><datalist id="font-families">@foreach(['Arial','system','Georgia','Atkinson Hyperlegible','Inter','Roboto','Open Sans','Lato','Montserrat'] as $family)<option value="{{ $family }}">@endforeach</datalist><small>Choose a suggestion or enter any font family name.</small></div>
                <div class="field"><label for="font_url">Custom font file URL (optional)</label><input id="font_url" name="font_url" type="url" value="{{ old('font_url',$settings['font_url']??'') }}" placeholder="https://example.com/fonts/my-font.woff2"><small>Direct HTTPS link to a WOFF or WOFF2 file, not a stylesheet. Leave blank to use fonts on the visitor’s device.</small></div>
                <div class="field"><label for="font_size">Base text size</label><select id="font_size" name="font_size" required>@foreach([14,15,16,17,18,19,20] as $size)<option value="{{ $size }}" @selected((int)old('font_size',$settings['font_size']??16)===$size)>{{ $size }} px</option>@endforeach</select></div>
            </div>
        </section>
        <section class="settings-group"><h2>Logo and favicon</h2><p>Upload a clear logo and a square icon. Logos: PNG, JPG or WebP, up to 4 MB. Favicons: PNG or ICO, up to 1 MB.</p>
            <div class="settings-fields">
                <div class="field"><label for="logo">Site logo <span class="optional">(optional)</span></label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">@if(!empty($settings['site_logo']))<img class="settings-preview-logo" src="{{ $settings['site_logo'] }}" alt="Current site logo">@endif</div>
                <div class="field"><label for="favicon">Favicon <span class="optional">(optional)</span></label><input id="favicon" type="file" name="favicon" accept="image/png,image/x-icon">@if(!empty($settings['site_favicon']))<img class="settings-preview-icon" src="{{ $settings['site_favicon'] }}" alt="Current site favicon">@endif</div>
            </div>
        </section>
        <div class="settings-save"><button type="submit"><i class="fas fa-check" aria-hidden="true"></i> Save appearance</button></div>
    </form>
    </section>
    @endif
    <section class="tab-panel" role="tabpanel" id="settings-panel-payments" aria-labelledby="settings-tab-payments" @if(auth()->user()->role==='admin')hidden @endif>
    @php $gatewayList=collect($gateways); $gatewayActive=$gatewayList->where('active',true)->count(); @endphp
    <section class="panel payment-settings" id="payment-methods">
        <div class="mel-panel-heading">
            <div>
                <h2>Payment gateways and methods</h2>
                <p>{{ $gatewayList->count() }} saved · {{ $gatewayActive }} shown to learners. Add verified payment destinations and clear learner instructions.</p>
            </div>
            <button type="button" class="secondary small" data-dialog-open="payment-settings-dialog">Configure</button>
        </div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Provider</th><th>Account or merchant</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($gateways as $gateway)<tr><td>{{ $gateway->name }}</td><td>{{ $gateway->provider_type }}</td><td>{{ $gateway->provider }}</td><td>{{ $gateway->account_number ? $maskAccount($gateway->account_number) : $gateway->merchant_code }}</td><td>{{ $gateway->amount?number_format($gateway->amount,2).' '.$gateway->currency:'—' }}</td><td>{{ $gateway->active?'Active':'Hidden' }}</td><td><div class="actions"><x-record-view-trigger :dialogId="'view-gateway-'.$gateway->id" :name="$gateway->name" /><form method="post" action="/admin/site-settings/payment-gateways/{{ $gateway->id }}" data-confirm="Delete this payment option?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete payment option" aria-label="Delete payment option"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form></div></td></tr>@empty<tr><td colspan="7">No payment options yet. Use Configure to add one.</td></tr>@endforelse
        </tbody></table></div>
    @foreach($gateways as $gateway)<x-record-view-dialog :id="'view-gateway-'.$gateway->id" :title="$gateway->name" :fields="array_merge([['label'=>'Name','value'=>$gateway->name],['label'=>'Payment type','value'=>$gateway->provider_type],['label'=>'Provider','value'=>$gateway->provider],['label'=>'Account or merchant','value'=>$gateway->account_number ? $maskAccount($gateway->account_number) : $gateway->merchant_code],['label'=>'Amount','value'=>$gateway->amount?number_format($gateway->amount,2).' '.$gateway->currency:null],['label'=>'Currency','value'=>$gateway->currency],['label'=>'Shown to learners','value'=>(bool) $gateway->active],['label'=>'API credentials','value'=>($gateway->credentials??null)?'Configured (encrypted)':'Not configured']], $gatewayDetails($gateway))" />@endforeach
    @foreach($gateways as $gateway)<details class="panel"><summary>{{ $gateway->name }} — details</summary>@include('partials.payment-details')<p>API credentials: {{ ($gateway->credentials??null)?'Configured (encrypted)':'Not configured' }}</p></details>@endforeach
    </section></section>
    @if(auth()->user()->role==='admin')
    <section class="tab-panel" role="tabpanel" id="settings-panel-ai" aria-labelledby="settings-tab-ai" hidden>
        @php $aiProviderName=config('ai.providers.'.($settings['ai_provider']??'openai').'.label')??($settings['ai_provider']??'openai'); @endphp
        <section class="panel payment-settings">
            <div class="mel-panel-heading">
                <div>
                    <h2>AI API connection</h2>
                    <p>Provider: {{ $aiProviderName }} · Model: {{ $settings['ai_api_model']??'Not set' }} · {{ $aiConfigured?'API key saved':'Built-in chat answers until a key is saved' }}</p>
                </div>
                <button type="button" class="secondary small" data-dialog-open="ai-settings-dialog">Configure</button>
            </div>
        </section>
    </section>
    @endif
    </div>

    <dialog class="form-dialog" id="payment-settings-dialog" aria-labelledby="payment-settings-dialog-title">
        <div class="dialog-heading"><h2 id="payment-settings-dialog-title">Payment gateway settings</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
        <div class="dialog-form">
            <form method="post" action="/admin/site-settings/payment-gateways" class="grid" data-payment-gateway>@csrf
                <div class="field"><label for="gateway-name">Display name</label><input id="gateway-name" name="name" required></div>
                <div class="field"><label for="gateway-type">Payment type</label><select id="gateway-type" name="provider_type" required>@foreach(array_keys(config('payments.types')) as $type)<option value="{{ $type }}" @selected(old('provider_type','IOTEC')===$type)>{{ $type }}</option>@endforeach</select></div>
                @php $gatewayFields=collect(config('payments.types'))->flatMap(fn($fields)=>$fields)->all(); @endphp
                <div class="field"><label><input type="checkbox" name="configure_api" value="1" @checked(old('configure_api'))> Configure provider API credentials</label><small>Saved credentials are encrypted. This does not turn on automatic payments. Payments still need manager confirmation.</small></div>
                <div class="field" data-gateway-api-environment><label for="gateway-environment">API environment</label><select id="gateway-environment" name="api_environment"><option value="sandbox">Sandbox / testing</option><option value="production" @selected(old('api_environment')==='production')>Production</option></select></div>
                @foreach($gatewayFields as $key=>$field)<div class="field" data-gateway-field="{{ $key }}"><label for="gateway-{{ $key }}">{{ $field['label'] }}</label><input id="gateway-{{ $key }}" name="{{ $key }}" type="{{ ($field['secret']??false)?'password':($field['type']??'text') }}" value="{{ ($field['secret']??false)?'':old($key) }}" data-secret-label="{{ $field['label'] }}" autocomplete="{{ ($field['secret']??false)?'new-password':'off' }}" maxlength="4096"></div>@endforeach
                <div class="field"><label for="gateway-currency">Currency</label><select id="gateway-currency" name="currency" required>@foreach(['UGX','USD','EUR','GBP','KES'] as $currency)<option value="{{ $currency }}">{{ $currency }}</option>@endforeach</select></div>
                <div class="field"><label for="gateway-amount">Amount <span class="optional">(optional)</span></label><input id="gateway-amount" type="number" min="0" step="0.01" name="amount"></div>
                <div class="field"><label for="gateway-instructions">Payment instructions</label><textarea id="gateway-instructions" name="instructions"></textarea></div>
                <div class="field"><label><input type="checkbox" name="active" value="1" checked> Show to learners</label></div>
                <div class="field"><button type="submit">Add payment option</button> <button type="button" class="secondary" data-dialog-close>Cancel</button></div>
            </form>
            <script type="application/json" id="payment-type-fields">{!! json_encode(config('payments.types'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        </div>
    </dialog>

    @if(auth()->user()->role==='admin')
    <dialog class="form-dialog" id="ai-settings-dialog" aria-labelledby="ai-settings-dialog-title">
        <div class="dialog-heading"><h2 id="ai-settings-dialog-title">AI API settings</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
        <div class="dialog-form">
            <form class="settings-form ai-settings-form" data-ai-settings method="post" action="/admin/site-settings/ai">@csrf @method('PUT')
                <h2>AI API connection</h2>
                <p>Pick a provider to fill in its URL and models, then add your API key. You can also enter a custom model or HTTPS endpoint. The key is encrypted and never shown again after saving. Chat messages and learning goals may be sent to this provider.</p>
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
                <div class="settings-save ai-settings-actions"><button class="secondary" type="button" data-ai-test><i class="fas fa-plug" aria-hidden="true"></i> Test connection</button><button type="submit"><i class="fas fa-lock" aria-hidden="true"></i> Save AI settings</button><button type="button" class="secondary" data-dialog-close>Cancel</button></div>
                <p class="ai-test-result" data-ai-test-result role="status" aria-live="polite"></p>
                <small>Testing sends a short request to the selected model and may use a little of your provider quota. It does not save changes.</small>
            </form>
            <script type="application/json" id="ai-provider-presets">{!! json_encode($aiProviders, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        </div>
    </dialog>
    @endif
    @php
        $settingsReopen = $errors->any()
            ? (old('ai_provider') !== null ? 'ai-settings-dialog' : (old('provider_type') !== null ? 'payment-settings-dialog' : null))
            : null;
    @endphp
    @if($settingsReopen)<div data-reopen-dialog="{{ $settingsReopen }}" hidden></div>@endif
</div>
@endsection
