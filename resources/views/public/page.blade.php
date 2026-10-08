@extends('layout')
@section('title',$page->title.' | Lwotowone Enterprises Ltd')
@section('description',$page->meta_description)
@section('content')
@if($page->slug==='about')
    <section class="about-hero">
        <span class="eyebrow">A little about us</span>
        <h1>Practical learning can open up new possibilities.</h1>
        <p class="about-lead">Lwotowone Enterprises Ltd is a Ugandan enterprise working with young people and communities to build useful skills, explore enterprise and create sustainable livelihoods.</p>
    </section>
    <div class="about-story-layout">
        <article class="about-story panel">
            <span class="eyebrow">Who we are</span>
            <div class="prose">{{ $page->body }}</div>
        </article>
        <aside class="about-belief">
            <span class="belief-mark">Our belief</span>
            <p>People learn best when knowledge meets practice, encouragement and the chance to try something for themselves.</p>
            <a href="/pages/approach">Read about our approach</a>
        </aside>
    </div>
    <section class="about-work">
        <span class="eyebrow">What brings us together</span>
        <h2>Learning, enterprise and opportunity belong in the same conversation.</h2>
        <div class="about-work-grid">
            <article><h3>Practical skills</h3><p>Hands-on learning helps people grow confidence and prepare for real tasks and responsibilities.</p></article>
            <article><h3>Enterprise thinking</h3><p>We encourage people to explore ideas, understand their resources and build step by step.</p></article>
            <article><h3>Support and connection</h3><p>Mentorship and community connections can help turn a new skill into a next step.</p></article>
        </div>
    </section>
    <section class="about-cta"><div><span class="eyebrow">Keep exploring</span><h2>There’s more than one way to begin.</h2><p>Take a look at our programmes and learning opportunities.</p></div><a class="button" href="/explore/programs">Explore programmes</a></section>
@else
    <span class="eyebrow">Lwotowone Enterprises Ltd</span>
    <h1>{{ $page->title }}</h1>
    <div class="prose page-content">{{ $page->body }}</div>
@endif
@endsection
