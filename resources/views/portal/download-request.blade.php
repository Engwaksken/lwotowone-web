{{-- Download request controls for one lesson or resource. Expects $type, $itemId, $downloadRequests and the $t(en, lg) translator. --}}
@php
    $request = $downloadRequests->get($type.'-'.$itemId);
    $state = \App\Services\Downloads::state($request);
    $max = \App\Services\Downloads::MAX_DOWNLOADS;
@endphp
<div class="download-request" data-download-state="{{ $state ?? 'none' }}">
    @if($state === 'approved')
        <a class="button small" href="/downloads/{{ $request->id }}"><i class="fas fa-download" aria-hidden="true"></i> {{ $t('Download','Wanula') }}</a>
        <small>{{ $t(($max - $request->downloads_used).' of '.$max.' downloads left, until '.\Illuminate\Support\Carbon::parse($request->expires_at)->format('d M Y'), ($max - $request->downloads_used).' ku '.$max.' ezisigaddeyo, okutuusa '.\Illuminate\Support\Carbon::parse($request->expires_at)->format('d M Y')) }}</small>
    @elseif($state === 'pending')
        <p class="muted"><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ $t('Download requested. Waiting for approval.','Osabye okuwanula. Linda okukkirizibwa.') }}</p>
    @else
        @if(in_array($state, ['rejected','revoked'], true))<p class="muted">{{ $t('Your download request was declined.','Okusaba kwo okuwanula kugaaniddwa.') }}@if($request->decision_note) {{ $request->decision_note }}@endif</p>
        @elseif(in_array($state, ['expired','used_up'], true))<p class="muted">{{ $t('Your download access has ended.','Ekiseera kyo eky’okuwanula kiweddeko.') }}</p>@endif
        <details class="download-request-form">
            <summary>{{ $state ? $t('Request download again','Saba nate okuwanula') : $t('Request download','Saba okuwanula') }}</summary>
            <form method="post" action="/portal/download-requests">@csrf
                <input type="hidden" name="item_type" value="{{ $type }}"><input type="hidden" name="item_id" value="{{ $itemId }}">
                <div class="field"><label for="download-message-{{ $type }}-{{ $itemId }}">{{ $t('Why do you need a copy? (optional)','Lwaki weetaaga kkopi? (si kya tteeka)') }}</label><textarea id="download-message-{{ $type }}-{{ $itemId }}" name="message" maxlength="1000" rows="2"></textarea></div>
                <button type="submit" class="secondary small">{{ $t('Send request','Weereza okusaba') }}</button>
            </form>
        </details>
    @endif
</div>
