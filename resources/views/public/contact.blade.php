@extends('layout')
@section('content')
@php
    $contact = fn($key) => trim((string) ($settings[$key] ?? ''));
    $email = $contact('contact_email'); $phone = $contact('contact_phone'); $address = $contact('contact_address'); $hours = $contact('contact_hours');
    $mapPlace = $contact('contact_map_location') ?: $address;
@endphp
<span class="eyebrow">Let's create opportunities together</span>
<h1>Contact us</h1>
<p>Ask about training, mentorship, partnerships or enterprise development.</p>
<div class="contact-layout">
    <form class="panel contact-form" method="post" action="/contact">
        @csrf
        <h2>Send us a message</h2>
        @foreach(['name'=>'Your name','email'=>'Email address'] as $f=>$label)
            <div class="field"><label for="{{ $f }}">{{ $label }}</label><input id="{{ $f }}" type="{{ $f==='email'?'email':'text' }}" name="{{ $f }}" value="{{ old($f) }}" autocomplete="{{ $f }}" required></div>
        @endforeach
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="7" required>{{ old('message') }}</textarea></div>
        <button type="submit">Send enquiry</button>
    </form>
    <aside class="contact-side" aria-label="Contact details and location">
        <section class="panel contact-details">
            <h2>Contact details</h2>
            @if($email||$phone||$address||$hours)
                <ul>
                    @if($phone)<li><i class="fas fa-phone" aria-hidden="true"></i><div><strong>Phone</strong><a href="tel:{{ preg_replace('/[^0-9+]/','',$phone) }}">{{ $phone }}</a></div></li>@endif
                    @if($email)<li><i class="fas fa-envelope" aria-hidden="true"></i><div><strong>Email</strong><a href="mailto:{{ $email }}">{{ $email }}</a></div></li>@endif
                    @if($address)<li><i class="fas fa-map-marker-alt" aria-hidden="true"></i><div><strong>Address</strong><span>{!! nl2br(e($address)) !!}</span></div></li>@endif
                    @if($hours)<li><i class="far fa-clock" aria-hidden="true"></i><div><strong>Office hours</strong><span>{!! nl2br(e($hours)) !!}</span></div></li>@endif
                </ul>
            @else
                <p class="muted">Send us a message using the form and we will get back to you.</p>
            @endif
        </section>
        @if($mapPlace)
            <section class="panel contact-map">
                <h2>Find us</h2>
                <iframe title="Map showing {{ $mapPlace }}" src="https://www.google.com/maps?q={{ urlencode($mapPlace) }}&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($mapPlace) }}" target="_blank" rel="noopener">Open in Google Maps <i class="fas fa-external-link-alt" aria-hidden="true"></i><span class="sr-only"> (opens in a new tab)</span></a>
            </section>
        @endif
    </aside>
</div>
@endsection
