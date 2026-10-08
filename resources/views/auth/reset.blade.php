@extends('auth.layout')
@section('title','Choose a new password | Lwotowone')
@section('content')
<section class="auth-card">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/reset-password">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="auth-heading"><span class="eyebrow">Account help</span><h1>Choose a new password</h1><p>Use at least 12 characters and choose a password you haven’t used before.</p></div>
        <div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email',$email) }}" autocomplete="email" required></div>
        @foreach(['password'=>'New password','password_confirmation'=>'Confirm new password'] as $field=>$label)<div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" type="password" name="{{ $field }}" minlength="12" autocomplete="new-password" required></div>@endforeach
        <button class="auth-submit" type="submit">Reset password</button>
        <p class="auth-switch"><a href="/login">Back to sign in</a></p>
    </form>
</section>
@endsection
