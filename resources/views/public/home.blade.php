@extends('layout')
@section('title','Lwotowone | Practical skills and new opportunities')
@php
    // Admins edit this page in Website pages (slug "home"): the title is the headline, the first paragraph of the body is the
    // intro under it, and the remaining paragraphs fill "How it works here". The settings and text below are only fallbacks.
    $homeParagraphs=$homePage?array_values(array_filter(array_map('trim',preg_split('/\R\s*\R/',(string)$homePage->body)),'strlen')):[];
    $homeTitle=$homePage?->title?:($site['hero_title']??'Skills you can use on Monday morning.');
    $homeLead=$homeParagraphs[0]??($site['hero_summary']??'We run hands-on courses in farming, trades and small business for young people in Uganda — and we stay with you after the course, with mentors and leads on work.');
    $homeAbout=$homePage?array_slice($homeParagraphs,1):[
        'Most of our learners already have an idea — a poultry unit, a tailoring stall, a phone repair bench. What’s missing is the know-how and someone to ask when things go wrong. So our courses are short and practical, built around the work itself.',
        'When a course ends you can still book time with a mentor. We post job and enterprise opportunities as they come in, and you can come back for the next course when you’re ready.',
    ];
    // Each programme gets an icon and colour from its category so the list is easy to scan.
    $programmeLook=function(?string $category){
        $c=strtolower((string)$category);
        return match(true){
            str_contains($c,'agri')||str_contains($c,'farm')=>['fa-seedling','mint'],
            str_contains($c,'tvet')||str_contains($c,'vocation')||str_contains($c,'trade')=>['fa-tools','sand'],
            str_contains($c,'entrepreneur')||str_contains($c,'enterprise')||str_contains($c,'business')=>['fa-store','rose'],
            str_contains($c,'youth')=>['fa-hands-helping','sky'],
            default=>['fa-graduation-cap','mint'],
        };
    };
@endphp
@section('description',$homePage?->meta_description?:'Explore practical learning, mentorship and enterprise support with Lwotowone Enterprises Ltd in Uganda.')
@section('content')
<section class="home-hero">
    <div class="home-hero-copy">
        @if(!$homePage&&!empty($site['hero_eyebrow']))<span class="eyebrow">{{ $site['hero_eyebrow'] }}</span>@endif
        <h1>{{ $homeTitle }}</h1>
        @if($homeLead!=='')<p class="home-lead">{{ $homeLead }}</p>@endif
        <div class="home-actions">
            <a class="button home-button-primary" href="/courses"><i class="fas fa-book-open" aria-hidden="true"></i> Browse courses</a>
            @auth<a class="button home-button-ghost" href="/dashboard">Go to my learning</a>@else<a class="button home-button-ghost" href="/register">Create an account</a>@endauth
        </div>
        <ul class="home-perks">
            <li><i class="fas fa-hammer" aria-hidden="true"></i> Short, hands-on courses</li>
            <li><i class="fas fa-user-friends" aria-hidden="true"></i> A mentor you can ask</li>
            <li><i class="fas fa-award" aria-hidden="true"></i> A certificate when you finish</li>
        </ul>
    </div>
    <section class="home-now" aria-labelledby="home-now-title">
        <h2 id="home-now-title">Happening now</h2>
        <ul>
            @if($nextEvent)
                <li><span class="home-now-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span><div><span class="home-now-kind">Next event</span><a href="/events/{{ $nextEvent->id }}">{{ $nextEvent->title }}</a><small>{{ \Illuminate\Support\Carbon::parse($nextEvent->starts_at)->format('D j M, g:i a') }} · {{ $nextEvent->location }}</small></div></li>
            @endif
            @if($opportunity)
                <li><span class="home-now-icon"><i class="fas fa-briefcase" aria-hidden="true"></i></span><div><span class="home-now-kind">Open opportunity</span><a href="/opportunities/{{ $opportunity->id }}">{{ $opportunity->title }}</a><small>{{ $opportunity->organisation }} · apply by {{ \Illuminate\Support\Carbon::parse($opportunity->deadline)->format('j M') }}</small></div></li>
            @endif
            @if($course=$courses->first())
                <li><span class="home-now-icon"><i class="fas fa-book" aria-hidden="true"></i></span><div><span class="home-now-kind">Newest course</span><a href="/courses/{{ $course->id }}">{{ $course->title }}</a><small>{{ $course->level }} · about {{ $course->duration_hours }} hours</small></div></li>
            @endif
            @if(!$nextEvent&&!$opportunity&&!$course)
                <li><span class="home-now-icon"><i class="fas fa-bell" aria-hidden="true"></i></span><div><span class="home-now-kind">Coming soon</span><span>We’re putting the next intake together. <a href="/contact">Ask us when it starts</a>.</span></div></li>
            @endif
        </ul>
        <a class="home-now-more" href="/events">See all events &rarr;</a>
    </section>
</section>

@if(($site['intro_video_enabled']??'0')==='1'&&!empty($site['intro_video']))
<section class="home-video" aria-labelledby="home-video-title">
    <div class="home-video-copy">
        <h2 id="home-video-title">{{ ($site['intro_video_title']??'')!==''?$site['intro_video_title']:'Meet Lwotowone' }}</h2>
        @if(($site['intro_video_summary']??'')!=='')<p>{{ $site['intro_video_summary'] }}</p>@endif
    </div>
    <div class="home-video-frame">
        <video controls preload="metadata" playsinline @if(!empty($site['intro_video_poster']))poster="{{ $site['intro_video_poster'] }}"@endif aria-describedby="home-video-title">
            <source src="{{ $site['intro_video'] }}" type="{{ str_ends_with($site['intro_video'],'.webm')?'video/webm':'video/mp4' }}">
            @if(!empty($site['intro_video_captions']))<track kind="captions" src="{{ $site['intro_video_captions'] }}" srclang="en" label="English">@endif
            Your browser cannot play this video. <a href="{{ $site['intro_video'] }}">Download the introduction video</a>.
        </video>
    </div>
</section>
@endif

<section class="home-programmes" aria-labelledby="home-programmes-title">
    <div class="home-section-heading"><h2 id="home-programmes-title">What would you like to learn?</h2><a href="/programs">All programmes &rarr;</a></div>
    @if($programs->isNotEmpty())
        <div class="home-tiles">
            @foreach($programs as $program)
                @php [$icon,$tint]=$programmeLook($program->category); @endphp
                <a class="home-tile home-tile-{{ $tint }}" href="/programs/{{ $program->id }}">
                    <span class="home-tile-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>
                    @if($program->category)<small>{{ $program->category }}</small>@endif
                    <strong>{{ $program->title }}</strong>
                    <span>{{ $program->summary }}</span>
                    <em>Explore &rarr;</em>
                </a>
            @endforeach
        </div>
    @else
        <p class="empty">Programme details are on the way. Check back soon.</p>
    @endif
</section>

@if($homeAbout)
<section class="home-about">
    <div class="home-about-mark" aria-hidden="true"><i class="fas fa-tools"></i></div>
    <div>
        <h2>How it works here</h2>
        <div class="home-about-text">
            @foreach($homeAbout as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            <p><a href="/approach">More about our approach</a></p>
        </div>
    </div>
</section>
@endif

<section class="home-courses" aria-labelledby="home-courses-title">
    <div class="home-section-heading"><h2 id="home-courses-title">Recent courses</h2><a href="/courses">All courses &rarr;</a></div>
    @if($courses->isNotEmpty())
        <div class="home-course-grid">
            @foreach($courses as $course)
                <a class="home-course" href="/courses/{{ $course->id }}">
                    <span class="home-course-meta"><span>{{ $course->level }}</span><span><i class="far fa-clock" aria-hidden="true"></i> {{ $course->duration_hours }} hours</span></span>
                    <strong>{{ $course->title }}</strong>
                    @if(!empty($course->summary))<span class="home-course-summary">{{ \Illuminate\Support\Str::limit($course->summary,120) }}</span>@endif
                    <em>View course &rarr;</em>
                </a>
            @endforeach
        </div>
    @else
        <div class="home-empty"><i class="fas fa-book-open" aria-hidden="true"></i><div><strong>New courses are on the way.</strong><p>Have a look at the programmes above to see what’s coming, or <a href="/contact">ask us when the next one starts</a>.</p></div></div>
    @endif
</section>

@if($posts->isNotEmpty())
<section class="home-stories" aria-labelledby="home-stories-title">
    <div class="home-section-heading"><h2 id="home-stories-title">From the field</h2><a href="/posts">All stories &rarr;</a></div>
    <div class="home-story-grid">
        @foreach($posts as $post)
            <article class="home-story"><i class="fas fa-quote-left" aria-hidden="true"></i><h3><a href="/posts/{{ $post->id }}">{{ $post->title }}</a></h3><p>{{ \Illuminate\Support\Str::limit($post->body,160) }}</p></article>
        @endforeach
    </div>
</section>
@endif

<section class="home-closing">
    <div>
        <h2>Join Lwotowone</h2>
        <p>Create an account to enrol in courses, book time with a mentor and hear about new opportunities. Not sure yet? <a href="/contact">Send us a question</a>.</p>
    </div>
    @auth<a class="button" href="/dashboard">Go to my learning</a>@else<a class="button" href="/register">Create an account</a>@endauth
</section>
@endsection
