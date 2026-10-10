<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Sign in or create a Lwotowone learning account.">
    <title>@yield('title','Lwotowone Account')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/layout.css') }}">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="{{ $siteSettings['site_favicon']??asset('favicon.ico') }}">
    <style>:root{--green:{{ $siteSettings['primary_color']??'#175742' }};--gold:{{ $siteSettings['accent_color']??'#dfb452' }};--site-font-size:{{ (int)($siteSettings['font_size']??16) }}px;--site-font-family:{{ match($siteSettings['font_family']??'Arial'){'system'=>'system-ui, sans-serif','Georgia'=>'Georgia, serif','Atkinson Hyperlegible'=>'Atkinson Hyperlegible, Arial, sans-serif',default=>'Arial, sans-serif'} }} }</style>
@include('partials.site-font')
</head>
<body class="auth-page">
    <a class="skip" href="#auth-main">Skip to form</a>
    <main class="auth-shell" id="auth-main">
        @yield('content')
        <footer class="auth-footer"><a class="button secondary auth-home-link" href="/">Back to website</a><p class="auth-copyright">© 2026 Lwotowone Enterprises Ltd · Uganda</p></footer>
    </main>
    <x-help-widget />
    <script src="{{ asset('assets/app.js') }}" defer></script>
</body>
</html>
