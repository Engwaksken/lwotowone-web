@extends('auth.layout')
@section('title','Change your password | Lwotowone')
@section('content')
<section class="auth-card">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/change-password">
        @csrf
        <div class="auth-heading"><span class="eyebrow">Secure your account</span><h1>Choose a new password</h1><p>Your temporary password must be replaced before you continue.</p></div>
        <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
        <button class="auth-submit" type="submit">Save new password</button>
    </form>
</section>
@endsection
