@extends('auth.layout')
@section('title','Create an account | Lwotowone')
@section('content')
<section class="auth-card auth-card-wide">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/register">
        @csrf
        <div class="auth-heading"><span class="eyebrow">A place to learn and grow</span><h1>Create your account</h1><p>Join Lwotowone to explore practical training, mentorship and opportunities.</p></div>
        <div class="auth-fields-grid">
            @foreach(['name'=>'Full name','email'=>'Email address','phone'=>'Phone number (optional)','district'=>'District (optional)','password'=>'Password','password_confirmation'=>'Confirm password'] as $field=>$label)
                <div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ str_contains($field,'password')?'password':($field==='email'?'email':($field==='phone'?'tel':'text')) }}" @if(!str_contains($field,'password'))value="{{ old($field) }}"@endif @if(str_contains($field,'password'))autocomplete="{{ $field==='password'?'new-password':'new-password' }}" minlength="12"@endif @if($field==='name')autocomplete="name"@elseif($field==='email')autocomplete="email"@endif @if(!in_array($field,['phone','district']))required @endif></div>
            @endforeach
        </div>
        <label class="consent-label"><input type="checkbox" name="consent" value="1" required><span>I agree to the <a href="/pages/terms">terms</a> and have read the <a href="/pages/privacy">privacy notice</a>.</span></label>
        <button class="auth-submit" type="submit">Create account</button>
        <p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
    </form>
</section>
@endsection
