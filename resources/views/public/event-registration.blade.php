@extends('layout')
@section('title',$event->title.' | Event registration')
@section('content')
<div class="page-wrap public-form-page"><span class="eyebrow">Event registration</span><h1 class="page-title">{{ $event->title }}</h1><p class="prose">{{ $event->description }}</p>
<section class="panel"><dl class="event-details"><div><dt>Starts</dt><dd>{{ \Carbon\Carbon::parse($event->starts_at)->format('d M Y, H:i') }}</dd></div><div><dt>Ends</dt><dd>{{ \Carbon\Carbon::parse($event->ends_at)->format('d M Y, H:i') }}</dd></div><div><dt>Location</dt><dd>{{ $event->location }}</dd></div><div><dt>Fee</dt><dd>@if($feeDue){{ number_format($event->payment_amount,2) }} {{ $currency }}@elseif((float)($event->payment_amount??0)>0)Free during the free access period @else Free @endif</dd></div>@if($event->capacity)<div><dt>Places remaining</dt><dd>{{ max(0,$event->capacity-$registered) }}</dd></div>@endif</dl></section>
@if($registration&&$registration->status!=='cancelled')<div class="notice">Your registration status: <strong>{{ ucfirst($registration->status) }}</strong>.</div>
@elseif(now()->gte($event->starts_at))<div class="notice">Registration is closed. The event has started.</div>
@elseif($event->capacity&&$registered>=$event->capacity)<div class="notice">This event is full.</div>
@elseif(auth()->check()&&auth()->user()->role!=='participant')<div class="notice">This registration form is for participants. Staff can manage the roster from the events workspace.</div>
@else
<form class="panel public-response-form" method="post" action="/events/{{ $event->registration_token }}/register">@csrf<h2>Register for this event</h2>
@guest<div class="field"><label for="event-name">Full name</label><input id="event-name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required></div><div class="field"><label for="event-email">Email</label><input id="event-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></div><div class="field"><label for="event-phone">Phone (optional)</label><input id="event-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></div><p class="muted">No account is required to register. Your contact details are available to the event team for registration and attendance.</p>@else<p>Register as <strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->email }}</p>@endguest
@if($feeDue)<p class="muted">This event costs <strong>{{ number_format($event->payment_amount,2) }} {{ $currency }}</strong>. Reserve your place, then pay with one of the methods below. The event team confirms payment.</p>@endif
<button type="submit">Reserve my place</button></form>
@endif
@if($feeDue)<section class="panel"><h2>How to pay</h2>@forelse($gateways as $gateway)<article class="panel"><h3>{{ $gateway->name }}</h3><p><strong>{{ $gateway->provider_type }}</strong>@if($gateway->provider) · {{ $gateway->provider }}@endif</p>@if($gateway->account_name)<p><strong>Account holder:</strong> {{ $gateway->account_name }}</p>@endif @if($gateway->account_number)<p><strong>Account / phone:</strong> {{ $gateway->account_number }}</p>@endif @if($gateway->merchant_code)<p><strong>Merchant code:</strong> {{ $gateway->merchant_code }}</p>@endif @include('partials.payment-details')@if($gateway->instructions)<p class="prose">{{ $gateway->instructions }}</p>@endif</article>@empty<p>Contact the event team for payment details.</p>@endforelse</section>
@endif</div>
@endsection
