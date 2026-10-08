@extends('auth.layout')
@section('title','Sign in | Lwotowone')
@section('content')
<section class="auth-card">
    <a class="auth-brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'auth-logo'])</a>
    @include('auth.notices')
    <form class="auth-form" method="post" action="/login">
        @csrf
        <div class="auth-heading"><span class="eyebrow">Welcome back</span><h1>Sign in to your account</h1><p>Pick up where you left off with your learning and practical work.</p></div>
        <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" placeholder="Enter your email address" required autofocus></div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required></div>
        <div class="auth-options"><label class="check-label"><input type="checkbox" name="remember" value="1"> Remember me</label><a href="/forgot-password">Forgot password?</a></div>
        <button class="auth-submit" type="submit">Sign in</button>
        <p class="auth-switch">New to Lwotowone? <a href="/register">Create an account</a></p>
    </form>
</section>
@endsection
