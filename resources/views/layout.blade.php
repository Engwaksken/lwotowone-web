<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="@yield('description','Skills for Life. Enterprise for Livelihoods. Opportunities for Youth.')">
    <title>@yield('title','Lwotowone Enterprises Ltd')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/layout.css') }}">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="site-page">
<a class="skip" href="#main">Skip to content</a>
<header>
    <a class="brand" href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'site-logo'])</a>
    <nav aria-label="Main navigation">
        <a href="/pages/about">About us</a><a href="/explore/programs">Programmes</a><a href="/explore/courses">Learn</a>
        <a href="/explore/opportunities">Opportunities</a><a href="/explore/events">Events</a><a href="/explore/posts">Stories</a><a href="/contact">Contact</a>
        @auth<a class="button small" href="/dashboard">Dashboard</a>@else<a href="/login">Sign in</a><a class="button small" href="/register">Join us</a>@endauth
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
                        'Settings'=>['settings'],
                    ];
                @endphp
                @foreach($sidebarGroups as $groupTitle=>$moduleKeys)
                    @php $visibleModules=collect($moduleKeys)->filter(fn($key)=>isset(config('modules')[$key])&&\App\Services\Catalog::allowed(auth()->user(),$key)); @endphp
                    @if($visibleModules->isNotEmpty())
                        <section class="sidebar-group"><span class="sidebar-heading">{{ $groupTitle }}</span>
                            @foreach($visibleModules as $key)<a href="/admin/{{ $key }}"><i class="fas {{ match($key){'pages'=>'fa-file-alt','programs'=>'fa-layer-group','courses'=>'fa-graduation-cap','lessons'=>'fa-book-open','assignments'=>'fa-tasks','resources'=>'fa-folder-open','skills'=>'fa-tools','users'=>'fa-users','slots'=>'fa-calendar-check','opportunities'=>'fa-briefcase','events'=>'fa-calendar-alt','announcements'=>'fa-bullhorn','posts'=>'fa-newspaper','settings'=>'fa-cog',default=>'fa-folder'} }}" aria-hidden="true"></i> {{ config("modules.$key.title") }}</a>@endforeach
                        </section>
                    @endif
                @endforeach
                @php
                    $reviewLinks=[];
                    if(auth()->user()->manager())$reviewLinks+=['practice_logs'=>'Verify skills','applications'=>'Applications','event_registrations'=>'Attendance','contacts'=>'Enquiries','audit_logs'=>'Audit trail'];
                    if(auth()->user()->manager()||auth()->user()->role==='instructor')$reviewLinks=['submissions'=>'Assess practical work']+$reviewLinks;
                    if(auth()->user()->manager()||auth()->user()->role==='mentor')$reviewLinks=['bookings'=>'Mentorship requests']+$reviewLinks;
                @endphp
                @if($reviewLinks)<section class="sidebar-group"><span class="sidebar-heading">Reviews and follow-up</span>@foreach($reviewLinks as $key=>$label)<a href="/admin/reviews/{{ $key }}"><i class="fas {{ match($key){'submissions'=>'fa-clipboard-check','practice_logs'=>'fa-tools','bookings'=>'fa-comments','applications'=>'fa-file-signature','event_registrations'=>'fa-user-check','contacts'=>'fa-inbox',default=>'fa-clipboard-list'} }}" aria-hidden="true"></i> {{ $label }}</a>@endforeach</section>@endif
                 @if(auth()->user()->manager())<section class="sidebar-group"><span class="sidebar-heading">Reports</span><a href="/admin/mel"><i class="fas fa-chart-line" aria-hidden="true"></i> MEL reports</a><a href="/admin/reports/impact.csv"><i class="fas fa-file-export" aria-hidden="true"></i> Export impact report</a></section>@endif
            @else
                <section class="sidebar-group"><span class="sidebar-heading">Learning</span><a href="/portal/learn"><i class="fas fa-book-open" aria-hidden="true"></i> My learning</a><a href="/portal/practice"><i class="fas fa-tools" aria-hidden="true"></i> Practical skills</a></section>
                <section class="sidebar-group"><span class="sidebar-heading">Grow and connect</span><a href="/portal/mentorship"><i class="fas fa-hands-helping" aria-hidden="true"></i> Mentorship</a><a href="/portal/opportunities"><i class="fas fa-briefcase" aria-hidden="true"></i> Opportunities</a><a href="/portal/enterprise"><i class="fas fa-store" aria-hidden="true"></i> Enterprise and earnings</a><a href="/portal/events"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Events</a></section>
                <section class="sidebar-group"><span class="sidebar-heading">Updates</span><a href="/portal/notifications"><i class="fas fa-bell" aria-hidden="true"></i> Notifications</a></section>
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

<footer class="site-footer">
    <div class="footer-main">
        <div class="footer-column footer-brand"><a href="/" aria-label="Lwotowone Enterprises Ltd home">@include('partials.brand-logo',['logoClass'=>'footer-logo'])</a><p>Practical learning, useful skills and support for young people building their livelihoods.</p></div>
        <div class="footer-column"><h2>About Lwotowone</h2><ul><li><a href="/pages/about">Who we are</a></li>@foreach($contentPages->whereNotIn('slug',['about','privacy','terms'])->take(4) as $navPage)<li><a href="/pages/{{ $navPage->slug }}">{{ $navPage->title }}</a></li>@endforeach</ul></div>
        <div class="footer-column"><h2>Explore</h2><ul><li><a href="/explore/programs">Programmes</a></li><li><a href="/explore/courses">Courses</a></li><li><a href="/explore/opportunities">Opportunities</a></li><li><a href="/explore/events">Events</a></li><li><a href="/explore/posts">Stories</a></li></ul></div>
        <div class="footer-column"><h2>Get in touch</h2><ul><li><a href="/contact">Contact us</a></li><li><a href="/register">Join Lwotowone</a></li><li><a href="/login">Sign in</a></li><li><a href="/pages/privacy">Privacy notice</a></li><li><a href="/pages/terms">Terms of use</a></li></ul></div>
    </div>
    <div class="footer-bottom"><p>© 2026 Lwotowone Enterprises Ltd · Uganda</p></div>
</footer>
<script src="{{ asset('assets/app.js') }}" defer></script>
</body>
</html>
