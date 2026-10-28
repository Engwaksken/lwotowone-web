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
@endphp
@section('description',$homePage?->meta_description?:'Explore practical learning, mentorship and enterprise support with Lwotowone Enterprises Ltd in Uganda.')
@section('content')
<section class="home-hero">
    <div class="home-hero-copy">
        @if(!$homePage&&!empty($site['hero_eyebrow']))<span class="eyebrow">{{ $site['hero_eyebrow'] }}</span>@endif
        <h1>{{ $homeTitle }}</h1>
        @if($homeLead!=='')<p class="home-lead">{{ $homeLead }}</p>@endif
        <div class="actions home-actions"><a class="button" href="/courses">See the courses</a><a class="home-text-link" href="/about">Who we are &rarr;</a></div>
    </div>
    <section class="home-now" aria-labelledby="home-now-title">
        <h2 id="home-now-title">Happening now</h2>
        <ul>
            @if($nextEvent)
                <li><span class="home-now-kind">Next event</span><a href="/events/{{ $nextEvent->id }}">{{ $nextEvent->title }}</a><small>{{ \Illuminate\Support\Carbon::parse($nextEvent->starts_at)->format('D j M, g:i a') }} · {{ $nextEvent->location }}</small></li>
            @endif
            @if($opportunity)
                <li><span class="home-now-kind">Open opportunity</span><a href="/opportunities/{{ $opportunity->id }}">{{ $opportunity->title }}</a><small>{{ $opportunity->organisation }} · apply by {{ \Illuminate\Support\Carbon::parse($opportunity->deadline)->format('j M') }}</small></li>
            @endif
            @if($course=$courses->first())
                <li><span class="home-now-kind">Newest course</span><a href="/courses/{{ $course->id }}">{{ $course->title }}</a><small>{{ $course->level }} · about {{ $course->duration_hours }} hours</small></li>
            @endif
            @if(!$nextEvent&&!$opportunity&&!$course)
                <li><span class="home-now-kind">Coming soon</span><span>We’re putting the next intake together. <a href="/contact">Ask us when it starts</a>.</span></li>
            @endif
        </ul>
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

@if($homeAbout)
<section class="home-about">
    <h2>How it works here</h2>
    <div class="home-about-text">
        @foreach($homeAbout as $paragraph)<p>{{ $paragraph }}</p>@endforeach
        <p><a href="/approach">More about our approach</a></p>
    </div>
</section>
@endif

<section class="home-programmes" aria-labelledby="home-programmes-title">
    <div class="home-section-heading"><h2 id="home-programmes-title">Our programmes</h2><a href="/programs">All programmes &rarr;</a></div>
    @if($programs->isNotEmpty())
        <ul class="home-list">
            @foreach($programs as $program)
                <li><a href="/programs/{{ $program->id }}"><strong>{{ $program->title }}</strong><span>{{ $program->summary }}</span></a>@if($program->category)<small>{{ $program->category }}</small>@endif</li>
            @endforeach
        </ul>
    @else
        <p class="empty">Programme details are on the way. Check back soon.</p>
    @endif
</section>

<section class="home-split">
    <div>
        <div class="home-section-heading"><h2>Recent courses</h2><a href="/courses">All courses &rarr;</a></div>
        @forelse($courses as $course)
            <a class="home-course" href="/courses/{{ $course->id }}"><strong>{{ $course->title }}</strong><small>{{ $course->level }} · {{ $course->duration_hours }} hours</small></a>
        @empty
            <p class="empty">New courses will be listed here.</p>
        @endforelse
    </div>
    <div>
        <div class="home-section-heading"><h2>From the field</h2><a href="/posts">All stories &rarr;</a></div>
        @forelse($posts as $post)
            <article class="home-story"><h3><a href="/posts/{{ $post->id }}">{{ $post->title }}</a></h3><p>{{ \Illuminate\Support\Str::limit($post->body,160) }}</p></article>
        @empty
            <p class="empty">We’ll share stories from learners and partners here.</p>
        @endforelse
    </div>
</section>

<section class="home-closing">
    <p><strong>Thinking about joining?</strong> Create a free account to enrol, or <a href="/contact">send us a question</a> first.</p>
    <a class="button" href="/register">Create an account</a>
</section>
@endsection
