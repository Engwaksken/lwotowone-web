<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description','Skills for Life. Enterprise for Livelihoods. Opportunities for Youth.')">
    <title>@yield('title','Lwotowone Enterprises Ltd')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/layout.css') }}">
    <link rel="icon" href="{{ $siteSettings['site_favicon']??asset('favicon.ico') }}">
    <style>:root{--green:{{ $siteSettings['primary_color']??'#175742' }};--gold:{{ $siteSettings['accent_color']??'#dfb452' }};--site-font-size:{{ (int)($siteSettings['font_size']??16) }}px;--site-font-family:{{ match($siteSettings['font_family']??'Arial'){'system'=>'system-ui, sans-serif','Georgia'=>'Georgia, serif','Atkinson Hyperlegible'=>'Atkinson Hyperlegible, Arial, sans-serif',default=>'Arial, sans-serif'} }} }</style>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    @include('partials.site-font')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="site-page">
<a class="skip" href="#main">Skip to content</a>
<header>
    <a class="brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'site-logo','siteSettings'=>$siteSettings])</a>
    <nav aria-label="Main navigation">
        @guest
        <a href="/pages/about">About us</a><a href="/explore/programs">Programmes</a><a href="/explore/courses">Learn</a>
        <a href="/explore/opportunities">Opportunities</a><a href="/explore/events">Events</a><a href="/explore/posts">Stories</a><a href="/contact">Contact</a>
        <a href="/login">Sign in</a><a class="button small" href="/register">Join us</a>
        <form class="header-search" method="get" action="/search" role="search"><input id="site-search" type="search" name="q" value="{{ request()->is('search')?request('q'):'' }}" placeholder="Search" aria-label="Search the site" required><button type="submit" aria-label="Search"><i class="fas fa-search" aria-hidden="true"></i></button></form>
        @else
        <a class="button small" href="/dashboard"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard</a>
        <a href="/profile"><i class="fas fa-user-circle" aria-hidden="true"></i> Profile</a>
        <form class="topbar-logout" method="post" action="/logout">@csrf<button class="secondary small" type="submit"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</button></form>
        @endif
    </nav>
</header>

<div class="shell">
    @auth
    <aside class="site-sidebar" aria-label="Account navigation">
        <div class="sidebar-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ ucwords(str_replace('_',' ',auth()->user()->role)) }}</small></div>
        <nav class="sidebar-nav">
            <section class="sidebar-group">
                <span class="sidebar-heading">Workspace</span>
                <a href="/dashboard"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Overview</a>
            </section>
            @if(auth()->user()->staff())
                @php
                    $sidebarGroups=[
                        'Learning and content'=>['pages','programs','courses','lessons','assignments','resources','skills'],
                        'People and opportunities'=>['users','slots','opportunities','events','announcements','posts'],
                    ];
                @endphp
                @foreach($sidebarGroups as $groupTitle=>$moduleKeys)
                    @php $visibleModules=collect($moduleKeys)->filter(fn($key)=>isset(config('modules')[$key])&&\App\Services\Catalog::allowed(auth()->user(),$key)); @endphp
                    @if($visibleModules->isNotEmpty())
                        <details class="sidebar-group sidebar-disclosure" data-sidebar-group="{{ \Illuminate\Support\Str::slug($groupTitle) }}" @if($visibleModules->contains(fn($key)=>request()->is('admin/'.$key.'*')||($key==='settings'&&request()->is('admin/site-settings')))) open @endif><summary class="sidebar-heading">{{ $groupTitle }}</summary>
                            @foreach($visibleModules as $key)<a href="/admin/{{ $key }}" @if(request()->is('admin/'.$key.'*')) aria-current="page" @endif><i class="fas {{ match($key){'pages'=>'fa-file-alt','programs'=>'fa-layer-group','courses'=>'fa-graduation-cap','lessons'=>'fa-book-open','assignments'=>'fa-tasks','resources'=>'fa-folder-open','skills'=>'fa-tools','users'=>'fa-users','slots'=>'fa-calendar-check','opportunities'=>'fa-briefcase','events'=>'fa-calendar-alt','announcements'=>'fa-bullhorn','posts'=>'fa-newspaper',default=>'fa-folder'} }}" aria-hidden="true"></i> {{ config("modules.$key.title") }}</a>@endforeach
                            @if($groupTitle==='Learning and content'&&auth()->user()->manager())<a href="/admin/enrollment" @if(request()->is('admin/enrollment*')) aria-current="page" @endif><i class="fas fa-user-graduate" aria-hidden="true"></i> Enrollment and cohorts</a>@endif
                        </details>
                    @endif
                @endforeach
                @if(auth()->user()->manager())<details class="sidebar-group sidebar-disclosure" data-sidebar-group="settings" @if(request()->is('admin/site-settings*')) open @endif><summary class="sidebar-heading">Settings</summary>@if(auth()->user()->role==='admin')<a href="/admin/site-settings#settings-panel-appearance"><i class="fas fa-palette" aria-hidden="true"></i> Website appearance</a>@endif<a href="/admin/site-settings#payment-methods"><i class="fas fa-credit-card" aria-hidden="true"></i> Payment gateways</a>@if(auth()->user()->role==='admin')<a href="/admin/site-settings#settings-panel-ai"><i class="fas fa-robot" aria-hidden="true"></i> AI API settings</a>@endif</details>@endif
                @php
                    $reviewLinks=[];
                    if(auth()->user()->manager())$reviewLinks+=['practice_logs'=>'Verify skills','applications'=>'Applications','event_registrations'=>'Attendance','contacts'=>'Enquiries','audit_logs'=>'Audit trail'];
                    if(auth()->user()->manager()||auth()->user()->role==='instructor')$reviewLinks=['submissions'=>'Assess practical work']+$reviewLinks;
                    if(auth()->user()->manager()||auth()->user()->role==='mentor')$reviewLinks=['bookings'=>'Mentorship requests']+$reviewLinks;
                @endphp
                @if($reviewLinks)<details class="sidebar-group sidebar-disclosure" data-sidebar-group="reviews-follow-up" @if(request()->is('admin/reviews/*')) open @endif><summary class="sidebar-heading">Reviews and follow-up</summary>@foreach($reviewLinks as $key=>$label)<a href="/admin/reviews/{{ $key }}" @if(request()->is('admin/reviews/'.$key,'admin/reviews/'.$key.'/*')) aria-current="page" @endif><i class="fas {{ match($key){'submissions'=>'fa-clipboard-check','practice_logs'=>'fa-tools','bookings'=>'fa-comments','applications'=>'fa-file-signature','event_registrations'=>'fa-user-check','contacts'=>'fa-inbox',default=>'fa-clipboard-list'} }}" aria-hidden="true"></i> {{ $label }}</a>@endforeach</details>@endif
                 @if(auth()->user()->manager())<details class="sidebar-group sidebar-disclosure" data-sidebar-group="reports" @if(request()->is('admin/mel')||request()->is('admin/reports/*')) open @endif><summary class="sidebar-heading">Reports</summary><a href="/admin/mel"><i class="fas fa-chart-line" aria-hidden="true"></i> MEL reports</a><a href="/admin/reports/impact.csv"><i class="fas fa-file-export" aria-hidden="true"></i> Export impact report</a></details>@endif
            @else
                <details class="sidebar-group sidebar-disclosure" data-sidebar-group="learning" @if(request()->is('portal/learn*')||request()->is('portal/practice*')) open @endif><summary class="sidebar-heading">Learning</summary><a href="/portal/learn" @if(request()->is('portal/learn*')) aria-current="page" @endif><i class="fas fa-book-open" aria-hidden="true"></i> My learning</a><a href="/portal/practice" @if(request()->is('portal/practice*')) aria-current="page" @endif><i class="fas fa-tools" aria-hidden="true"></i> Practical skills</a></details>
                <details class="sidebar-group sidebar-disclosure" data-sidebar-group="grow-connect" @if(request()->is('portal/mentorship*')||request()->is('portal/opportunities*')||request()->is('portal/enterprise*')||request()->is('portal/events*')) open @endif><summary class="sidebar-heading">Grow and connect</summary><a href="/portal/mentorship" @if(request()->is('portal/mentorship*')) aria-current="page" @endif><i class="fas fa-hands-helping" aria-hidden="true"></i> Mentorship</a><a href="/portal/opportunities" @if(request()->is('portal/opportunities*')) aria-current="page" @endif><i class="fas fa-briefcase" aria-hidden="true"></i> Opportunities</a><a href="/portal/enterprise" @if(request()->is('portal/enterprise*')) aria-current="page" @endif><i class="fas fa-store" aria-hidden="true"></i> Enterprise and earnings</a><a href="/portal/events" @if(request()->is('portal/events*')) aria-current="page" @endif><i class="fas fa-calendar-alt" aria-hidden="true"></i> Events</a></details>
                <details class="sidebar-group sidebar-disclosure" data-sidebar-group="updates" @if(request()->is('portal/notifications*')) open @endif><summary class="sidebar-heading">Updates</summary><a href="/portal/notifications" @if(request()->is('portal/notifications*')) aria-current="page" @endif><i class="fas fa-bell" aria-hidden="true"></i> Notifications</a></details>
            @endif
            <section class="sidebar-group"><span class="sidebar-heading">Account</span><a href="/profile"><i class="fas fa-user-circle" aria-hidden="true"></i> My profile</a></section>
        </nav>
        <form class="sidebar-signout" method="post" action="/logout">@csrf<button class="secondary">Sign out</button></form>
    </aside>
    @endauth
    <main id="main">
        @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>

@unless(request()->is('dashboard','admin/*','portal/*','learning/*','profile','certificates/*'))
<footer class="site-footer">
    <div class="footer-main">
        <div class="footer-column footer-brand"><a href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'footer-logo','siteSettings'=>$siteSettings])</a><p>Practical learning, useful skills and support for young people building their livelihoods.</p></div>
        <div class="footer-column"><h2>About Lwotowone</h2><ul><li><a href="/pages/about">Who we are</a></li>@foreach($contentPages->whereNotIn('slug',['about','privacy','terms'])->take(4) as $navPage)<li><a href="/pages/{{ $navPage->slug }}">{{ $navPage->title }}</a></li>@endforeach</ul></div>
        <div class="footer-column"><h2>Explore</h2><ul><li><a href="/explore/programs">Programmes</a></li><li><a href="/explore/courses">Courses</a></li><li><a href="/explore/opportunities">Opportunities</a></li><li><a href="/explore/events">Events</a></li><li><a href="/explore/posts">Stories</a></li></ul></div>
        <div class="footer-column"><h2>Get in touch</h2><ul><li><a href="/contact">Contact us</a></li><li><a href="/faq">Frequently asked questions</a></li><li><a href="/user-guide">User guide</a></li><li><a href="/register">Join Lwotowone</a></li><li><a href="/login">Sign in</a></li><li><a href="/pages/privacy">Privacy notice</a></li><li><a href="/pages/terms">Terms of use</a></li></ul></div>
    </div>
    <div class="footer-bottom"><p>© 2026 Lwotowone Enterprises Ltd · Uganda</p></div>
</footer>
@endunless
<script src="{{ asset('assets/app.js') }}" defer></script>
@include('partials.help-tools')
</body>
</html>
