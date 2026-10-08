@extends('auth.layout')
@section('title','Reset your password | Lwotowone')
@section('content')
<section class="auth-card">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/forgot-password">
        @csrf
        <div class="auth-heading"><span class="eyebrow">Account help</span><h1>Reset your password</h1><p>Enter the email address on your account. If it’s registered, we’ll send instructions to reset your password.</p></div>
        <div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus></div>
        <button class="auth-submit" type="submit">Send reset instructions</button>
        <p class="auth-switch"><a href="/login">Back to sign in</a></p>
    </form>
</section>
@endsection
