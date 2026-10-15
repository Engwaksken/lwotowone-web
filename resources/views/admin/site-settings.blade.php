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
        @if(auth()->user()->role==='admin')<button type="button" role="tab" id="settings-tab-email" aria-controls="settings-panel-email" aria-selected="false" tabindex="-1">Email settings</button><button type="button" role="tab" id="settings-tab-backups" aria-controls="settings-panel-backups" aria-selected="false" tabindex="-1">Backups</button>@endif
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
    @php $freeEnds=\App\Services\FreeAccess::endsAt(); $freeActive=\App\Services\FreeAccess::active(); @endphp
    <section class="panel free-access-settings" id="free-access">
        <div class="mel-panel-heading">
            <div>
                <h2>Free access period</h2>
                <p>@if($freeActive)<span class="status-badge" data-status="published">Free access on</span> @if($freeEnds)Learners use all courses, materials and events free until <strong>{{ $freeEnds->format('d M Y, H:i') }}</strong>. Payment is required after that.@else Learners use all courses, materials and events free. No end date is set, so payment is not required yet.@endif @else<span class="status-badge" data-status="closed">Free access ended</span> The free period ended on <strong>{{ $freeEnds->format('d M Y, H:i') }}</strong>. Learners now pay or subscribe to continue, and event payment amounts apply.@endif</p>
            </div>
        </div>
        <form method="post" action="/admin/site-settings/free-access" class="inline-form">@csrf @method('PUT')
            <div class="field">
                <label for="free-access-ends-at">Free access ends (optional)</label>
                <input id="free-access-ends-at" type="datetime-local" name="free_access_ends_at" value="{{ old('free_access_ends_at',$freeEnds?->format('Y-m-d\TH:i')) }}">
                <small>Set a date to activate the expiry. Leave blank to keep everything free with no end date.</small>
            </div>
            <div class="actions"><button type="submit">Save free access period</button>@if($freeActive)<button type="submit" class="secondary" name="end_now" value="1" data-confirm="End free access now? Learners without confirmed payment will be asked to pay.">End free access now</button>@endif</div>
        </form>
    </section>
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
    @if(auth()->user()->role==='admin')
    <section class="tab-panel" role="tabpanel" id="settings-panel-email" aria-labelledby="settings-tab-email" hidden>
        <section class="panel" id="email-settings">
            <h2>Email delivery</h2>
            <p>Configure SMTP delivery for verification links, account notifications and platform email. Secrets are encrypted and never shown after saving.</p>
            <form class="settings-form" method="post" action="/admin/site-settings/email">
                @csrf @method('PUT')
                <div class="settings-fields">
                    <div class="field"><label for="mail-driver">Mail transport</label><select id="mail-driver" name="mail_driver" required><option value="smtp" @selected(old('mail_driver',$settings['mail_driver']??'smtp')==='smtp')>SMTP</option><option value="log" @selected(old('mail_driver',$settings['mail_driver']??'smtp')==='log')>Log only (testing)</option></select></div>
                    <div class="field"><label for="mail-host">SMTP host</label><input id="mail-host" name="mail_host" value="{{ old('mail_host',$settings['mail_host']??'') }}" placeholder="smtp.example.com" maxlength="255"></div>
                    <div class="field"><label for="mail-port">SMTP port</label><input id="mail-port" name="mail_port" type="number" min="1" max="65535" value="{{ old('mail_port',$settings['mail_port']??587) }}"></div>
                    <div class="field"><label for="mail-scheme">Encryption</label><select id="mail-scheme" name="mail_scheme"><option value="smtp" @selected(old('mail_scheme',$settings['mail_scheme']??'smtp')==='smtp')>STARTTLS</option><option value="smtps" @selected(old('mail_scheme',$settings['mail_scheme']??'smtp')==='smtps')>SSL/TLS</option></select></div>
                    <div class="field"><label for="mail-username">SMTP username</label><input id="mail-username" name="mail_username" value="{{ old('mail_username',$settings['mail_username']??'') }}" autocomplete="off" maxlength="255"></div>
                    <div class="field"><label for="mail-password">SMTP password {{ !empty($settings['mail_password'])?'(configured; leave blank to keep it)':'' }}</label><input id="mail-password" type="password" name="mail_password" autocomplete="new-password" maxlength="4096" placeholder="{{ !empty($settings['mail_password'])?'Saved securely':'SMTP password' }}"></div>
                    @if(!empty($settings['mail_password']))<div class="field"><label><input type="checkbox" name="clear_mail_password" value="1"> Clear saved SMTP password</label></div>@endif
                    <div class="field"><label for="mail-from-address">From email address</label><input id="mail-from-address" type="email" name="mail_from_address" value="{{ old('mail_from_address',$settings['mail_from_address']??config('mail.from.address')) }}" required maxlength="190"></div>
                    <div class="field"><label for="mail-from-name">From name</label><input id="mail-from-name" name="mail_from_name" value="{{ old('mail_from_name',$settings['mail_from_name']??config('mail.from.name')) }}" required maxlength="190"></div>
                </div>
                <div class="settings-save"><button type="submit">Save email settings</button></div>
            </form>
            <form method="post" action="/admin/site-settings/email/test" class="actions">@csrf<button class="secondary" type="submit">Send test email to {{ auth()->user()->email }}</button></form>
        </section>
    </section>
    <section class="tab-panel" role="tabpanel" id="settings-panel-backups" aria-labelledby="settings-tab-backups" hidden>
        <div class="tabs settings-subtabs" data-tabs>
            <div class="tab-list" role="tablist" aria-label="Backup options">
                <button type="button" role="tab" id="backup-tab-local" aria-controls="backup-panel-local" aria-selected="true">Local backups</button>
                <button type="button" role="tab" id="backup-tab-remote" aria-controls="backup-settings" aria-selected="false" tabindex="-1">Remote storage</button>
            </div>
            <section class="tab-panel" role="tabpanel" id="backup-settings" aria-labelledby="backup-tab-remote" hidden>
                <h2>Remote backup destination (S3-compatible)</h2>
                <p>Configure AWS S3, Cloudflare R2, DigitalOcean Spaces or another S3-compatible service. Credentials are encrypted at rest.</p>
                <form class="settings-form" method="post" action="/admin/site-settings/backups">
                    @csrf @method('PUT')
                    <div class="settings-fields">
                        <div class="field"><label for="backup-endpoint">S3 endpoint URL</label><input id="backup-endpoint" type="url" name="backup_s3_endpoint" value="{{ old('backup_s3_endpoint',$settings['backup_s3_endpoint']??'') }}" placeholder="https://s3.region.amazonaws.com" required maxlength="500"></div>
                        <div class="field"><label for="backup-bucket">Bucket</label><input id="backup-bucket" name="backup_s3_bucket" value="{{ old('backup_s3_bucket',$settings['backup_s3_bucket']??'') }}" required maxlength="255"></div>
                        <div class="field"><label for="backup-region">Region</label><input id="backup-region" name="backup_s3_region" value="{{ old('backup_s3_region',$settings['backup_s3_region']??'auto') }}" required maxlength="100"></div>
                        <div class="field"><label for="backup-access-key">Access key ID</label><input id="backup-access-key" name="backup_s3_access_key" value="{{ old('backup_s3_access_key',$settings['backup_s3_access_key']??'') }}" autocomplete="off" required maxlength="255"></div>
                        <div class="field"><label for="backup-secret">Secret access key {{ !empty($settings['backup_s3_secret'])?'(configured; leave blank to keep it)':'' }}</label><input id="backup-secret" type="password" name="backup_s3_secret" autocomplete="new-password" maxlength="4096" placeholder="{{ !empty($settings['backup_s3_secret'])?'Saved securely':'Secret access key' }}"></div>
                        @if(!empty($settings['backup_s3_secret']))<div class="field"><label><input type="checkbox" name="clear_backup_s3_secret" value="1"> Clear saved secret</label></div>@endif
                        <div class="field"><label for="backup-prefix">Remote folder / prefix</label><input id="backup-prefix" name="backup_s3_prefix" value="{{ old('backup_s3_prefix',$settings['backup_s3_prefix']??'backups') }}" maxlength="255"></div>
                    </div>
                    <div class="settings-save"><button type="submit">Save remote destination</button></div>
                </form>
            </section>
            <section class="tab-panel" role="tabpanel" id="backup-panel-local" aria-labelledby="backup-tab-local">
                <h2>Create a backup</h2>
                <p>Manual archives include a database export and uploaded files. Local copies are private to the server; you can also upload to the configured remote destination.</p>
                <form method="post" action="/admin/site-settings/backups/run" class="grid">@csrf
                    <div class="field"><label for="backup-destination">Save destination</label><select id="backup-destination" name="destination" required><option value="local">Local storage</option><option value="remote">Remote S3-compatible storage</option><option value="both">Both local and remote</option></select></div>
                    <div class="field"><button type="submit">Create backup now</button></div>
                </form>
                <h3>Recent local backups</h3>
                <div class="table-wrap"><table><thead><tr><th>Archive</th><th>Size</th><th>Created</th><th>Action</th></tr></thead><tbody>
                    @forelse($localBackups as $backup)<tr><td>{{ $backup['name'] }}</td><td>{{ number_format($backup['size']/1048576,2) }} MB</td><td>{{ \Carbon\Carbon::createFromTimestamp($backup['modified'])->format('d M Y H:i') }}</td><td><a class="button small" href="/admin/site-settings/backups/{{ rawurlencode($backup['name']) }}">Download</a></td></tr>@empty<tr><td colspan="4">No local backups yet.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        </div>
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
        $openSettingsTab = session('open_settings_tab') ?? (old('mail_driver') !== null ? 'email' : ((old('backup_s3_endpoint') !== null || old('destination') !== null) ? 'backups' : null));
        $openBackupTab = session('open_backup_tab') ?? (old('backup_s3_endpoint') !== null ? 'backup-tab-remote' : null);
    @endphp
    @if($settingsReopen)<div data-reopen-dialog="{{ $settingsReopen }}" hidden></div>@endif
    @if($openSettingsTab)<div data-open-settings-tab="{{ $openSettingsTab }}" hidden></div>@endif
    @if($openBackupTab)<div data-open-tab-id="{{ $openBackupTab }}" hidden></div>@endif
</div>
@endsection
