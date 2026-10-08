<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in or create a Lwotowone learning account.">
    <title>@yield('title','Lwotowone Account')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/layout.css') }}">
</head>
<body class="auth-page">
    <a class="skip" href="#auth-main">Skip to form</a>
    <main class="auth-shell" id="auth-main">
        @yield('content')
        <footer class="auth-footer"><p class="auth-copyright">© 2026 Lwotowone Enterprises Ltd · Uganda</p></footer>
    </main>
    <script src="{{ asset('assets/app.js') }}" defer></script>
</body>
</html>
