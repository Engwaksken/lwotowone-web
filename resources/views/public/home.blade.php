@extends('layout')
@section('title','Lwotowone | Practical skills and new opportunities')
@section('description','Explore practical learning, mentorship and enterprise support with Lwotowone Enterprises Ltd in Uganda.')
@section('content')
<section class="home-hero">
    <div class="home-hero-copy">
        <span class="eyebrow">{{ $site['hero_eyebrow']??'Learning that leads somewhere' }}</span>
        <h1>{{ $site['hero_title']??'Learn something useful. Build something of your own.' }}</h1>
        <p class="home-lead">{{ $site['hero_summary']??'At Lwotowone, young people learn by doing. We bring practical training, mentorship and enterprise support together so a skill can grow into a livelihood.' }}</p>
        <div class="actions home-actions"><a class="button" href="/explore/programs">Explore our programmes</a><a class="button secondary" href="/pages/about">Get to know us</a></div>
        <p class="home-note">Based in Uganda. Focused on practical skills, enterprise and opportunity.</p>
    </div>
    <aside class="home-journey" aria-label="How learning can grow into opportunity">
        <span class="journey-label">A good place to begin</span>
        <h2>Start with what you can do.</h2>
        <p>Build confidence one practical step at a time, with people around you who want to see you succeed.</p>
        <ol class="journey-list">
            <li><span>01</span><div><strong>Learn</strong><small>Pick up knowledge you can use.</small></div></li>
            <li><span>02</span><div><strong>Practise</strong><small>Try it, improve and get support.</small></div></li>
            <li><span>03</span><div><strong>Grow</strong><small>Put your skills to work in life and business.</small></div></li>
        </ol>
    </aside>
</section>

<section class="home-intro">
    <span class="eyebrow">Skills for everyday life</span>
    <h2>Learning should feel connected to the real world.</h2>
    <p>It might begin with a new skill, a mentor’s advice or an idea you want to test. We help bring those pieces together through practical education, vocational training and enterprise development.</p>
</section>

<section class="home-pillars" aria-label="Ways to take your next step">
    <article><span class="pillar-number">01</span><h3>Build a useful skill</h3><p>Explore hands-on learning shaped around work, business and community life.</p><a href="/explore/courses">Browse courses</a></article>
    <article><span class="pillar-number">02</span><h3>Learn with people</h3><p>Get guidance, practise together and build confidence from experience.</p><a href="/pages/approach">Our approach</a></article>
    <article><span class="pillar-number">03</span><h3>Make an idea real</h3><p>Take the next step towards an enterprise, a job or a new opportunity.</p><a href="/explore/opportunities">See opportunities</a></article>
</section>

<section class="home-explore">
    <div class="home-section-heading"><div><span class="eyebrow">Take a look around</span><h2>Find something that speaks to you.</h2></div><p>Explore a programme, try a course or hear from the people and communities we work with.</p></div>
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Explore Lwotowone">
            <button type="button" role="tab" id="tab-programmes" aria-controls="panel-programmes" aria-selected="true">Programmes</button>
            <button type="button" role="tab" id="tab-courses" aria-controls="panel-courses" aria-selected="false" tabindex="-1">Courses</button>
            <button type="button" role="tab" id="tab-stories" aria-controls="panel-stories" aria-selected="false" tabindex="-1">Stories</button>
        </div>
        <section class="tab-panel" role="tabpanel" id="panel-programmes" aria-labelledby="tab-programmes"><div class="grid">@forelse($programs as $program)<article class="card home-card"><span class="tag">{{ $program->category }}</span><h3>{{ $program->title }}</h3><p>{{ $program->summary }}</p><a href="/explore/programs/{{ $program->id }}">Learn about this programme</a></article>@empty<div class="empty">We’re preparing information about our programmes. Please check back soon.</div>@endforelse</div></section>
        <section class="tab-panel" role="tabpanel" id="panel-courses" aria-labelledby="tab-courses" hidden><div class="grid">@forelse($courses as $course)<article class="card home-card"><span class="tag">{{ $course->level }} · {{ $course->duration_hours }} hours</span><h3>{{ $course->title }}</h3><p>{{ $course->summary }}</p><a href="/explore/courses/{{ $course->id }}">View course details</a></article>@empty<div class="empty">New courses will be announced here.</div>@endforelse</div></section>
        <section class="tab-panel" role="tabpanel" id="panel-stories" aria-labelledby="tab-stories" hidden><div class="grid">@forelse($posts as $post)<article class="card home-card"><span class="eyebrow">From our community</span><h3>{{ $post->title }}</h3><p>{{ \Illuminate\Support\Str::limit($post->body,180) }}</p><a href="/explore/posts/{{ $post->id }}">Read the story</a></article>@empty<div class="empty">Stories from our work will appear here.</div>@endforelse</div></section>
    </div>
</section>

<section class="home-closing">
    <div><span class="eyebrow">Your next step can start small</span><h2>Bring your curiosity. We’ll help you find a place to begin.</h2><p>Join a learning community where practical experience, encouragement and new ideas have room to grow.</p></div>
    <a class="button" href="/register">Join Lwotowone</a>
</section>
@endsection
