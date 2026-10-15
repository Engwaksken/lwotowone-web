@extends('auth.layout')
@section('title','Create an account | Lwotowone')
@section('content')
<section class="auth-card auth-card-wide">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/register" data-register-form>
        @csrf
        <div class="auth-heading"><span class="eyebrow">A place to learn and grow</span><h1>Create your account</h1><p>Join Lwotowone to find training, mentors and opportunities.</p></div>
        <p class="form-note"><span class="required-marker" aria-hidden="true">*</span> Required</p>
        <div class="auth-fields-grid">
            <div class="field">
                <label for="name">Full name <span class="required-marker" aria-hidden="true">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required aria-required="true" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<p class="field-error" id="name-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="email">Email address <span class="required-marker" aria-hidden="true">*</span></label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required aria-required="true" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="phone">Phone number <span class="required-marker" aria-hidden="true">*</span></label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required aria-required="true" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                @error('phone')<p class="field-error" id="phone-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="district">District <span class="optional">(optional)</span></label>
                <input id="district" name="district" type="text" value="{{ old('district') }}" @error('district') aria-invalid="true" aria-describedby="district-error" @enderror>
                @error('district')<p class="field-error" id="district-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Password <span class="required-marker" aria-hidden="true">*</span></label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-required="true" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p class="field-error" id="password-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password <span class="required-marker" aria-hidden="true">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required aria-required="true" @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
                @error('password_confirmation')<p class="field-error" id="password_confirmation-error" role="alert">{{ $message }}</p>@enderror
            </div>
        </div>
        @php
            $policyDocs=[
                'terms'=>['title'=>$policies['terms']->title??'Terms of use','body'=>$policies['terms']->body??"By creating an account you agree to use Lwotowone services lawfully and respectfully, to give accurate information about yourself, and to keep your password private.

Course materials are provided for your personal learning. Do not copy, resell or share them without permission.

We may suspend accounts that misuse the platform, harass other people or try to access information that is not theirs.

Contact us if you have questions about these terms."],
                'privacy'=>['title'=>$policies['privacy']->title??'Privacy notice','body'=>$policies['privacy']->body??"We collect the details you give us, such as your name, email, phone number and learner profile, to run your training, track progress and connect you with mentors and opportunities.

Your information is shared only with Lwotowone staff, instructors, mentors and partners who support your learning, or when the law requires it.

We keep your data secure and only for as long as needed for these purposes.

You can ask to see, correct or delete your information by contacting us."],
            ];
        @endphp
        <div class="policy-reading" data-policy-reading>
            <p class="policy-reading-intro">Before you create your account, please read both documents. Open each one and scroll to the end.</p>
            <div class="policy-reading-list">
                @foreach($policyDocs as $key=>$doc)
                    <button type="button" class="policy-open secondary" data-policy-open="{{ $key }}" aria-haspopup="dialog"><i class="fas fa-file-alt" aria-hidden="true"></i> <span>{{ $doc['title'] }}</span><span class="policy-status" data-policy-status="{{ $key }}">@if(old($key.'_read'))<i class="fas fa-check-circle" aria-hidden="true"></i> Read @else Not read yet @endif</span></button>
                    <input type="hidden" name="{{ $key }}_read" value="{{ old($key.'_read')?'1':'' }}" data-policy-input="{{ $key }}">
                @endforeach
            </div>
            @error('terms_read')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('privacy_read')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <label class="consent-label"><input type="checkbox" name="consent" value="1" required aria-required="true" data-policy-consent @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror><span>I have read and agree to the terms of use and the privacy notice.</span></label>
        @error('consent')<p class="field-error" id="consent-error" role="alert">{{ $message }}</p>@enderror
        <button class="auth-submit" type="submit">Create account</button>
        <p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
    </form>
    @foreach($policyDocs as $key=>$doc)
        <dialog class="form-dialog policy-dialog" id="policy-{{ $key }}" aria-labelledby="policy-{{ $key }}-title" data-policy-dialog="{{ $key }}">
            <div class="dialog-heading"><h2 id="policy-{{ $key }}-title">{{ $doc['title'] }}</h2><button type="button" class="secondary small" data-dialog-close>Close</button></div>
            <div class="policy-body prose" tabindex="0" data-policy-body>{{ $doc['body'] }}</div>
            <div class="dialog-actions policy-actions"><p class="policy-hint" data-policy-hint>Scroll to the end to continue.</p><button type="button" data-policy-accept disabled>I have read this</button></div>
        </dialog>
    @endforeach
</section>
@endsection
