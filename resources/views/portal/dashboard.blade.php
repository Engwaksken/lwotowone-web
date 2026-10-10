@extends('layout')
@section('content')
<div class="page-wrap">
    <span class="eyebrow">Learn · Practice · Create · Earn</span>
    <h1 class="page-title">Hello, {{ $data['user']['name'] }}</h1>
    <p class="page-intro">Take your next step toward a skill, a business and a steady income.</p>
    <div class="stats">
        <div class="stat"><span class="stat-icon"><i class="fas fa-book-open" aria-hidden="true"></i></span><strong>{{ count($data['enrolments']) }}</strong><span>Enrolled courses</span></div>
        <div class="stat"><span class="stat-icon"><i class="fas fa-check-double" aria-hidden="true"></i></span><strong>{{ count($data['lesson_progress']) }}</strong><span>Lessons completed</span></div>
        <div class="stat"><span class="stat-icon"><i class="fas fa-tools" aria-hidden="true"></i></span><strong>{{ collect($data['practice_logs'])->where('status','verified')->count() }}</strong><span>Verified practice logs</span></div>
        <div class="stat"><span class="stat-icon"><i class="fas fa-store" aria-hidden="true"></i></span><strong>{{ count($data['enterprises']) }}</strong><span>Enterprise ideas</span></div>
        <div class="stat"><span class="stat-icon"><i class="fas fa-coins" aria-hidden="true"></i></span><strong>{{ number_format(collect($data['transactions'])->where('type','income')->sum('amount')) }}</strong><span>Recorded income · UGX</span></div>
    </div>
    <h2>Your workspace</h2>
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Your workspace">
            <button type="button" role="tab" id="tab-learning" aria-controls="panel-learning" aria-selected="true">Learning</button>
            <button type="button" role="tab" id="tab-growth" aria-controls="panel-growth" aria-selected="false" tabindex="-1">Enterprise and support</button>
            <button type="button" role="tab" id="tab-community" aria-controls="panel-community" aria-selected="false" tabindex="-1">Community</button>
        </div>
        <section class="tab-panel" role="tabpanel" id="panel-learning" aria-labelledby="tab-learning"><div class="grid">
            <a class="card" href="/portal/learn"><span class="eyebrow">Learning</span><h3>Build your knowledge</h3><p>Continue a course or explore new training.</p></a>
            <a class="card" href="/portal/practice"><span class="eyebrow">Practice</span><h3>Put skills into practice</h3><p>Log activities and track your experience.</p></a>
        </div></section>
        <section class="tab-panel" role="tabpanel" id="panel-growth" aria-labelledby="tab-growth" hidden><div class="grid">
            <a class="card" href="/portal/enterprise"><span class="eyebrow">Enterprise</span><h3>Create and grow</h3><p>Develop an idea and track your earnings.</p></a>
            <a class="card" href="/portal/mentorship"><span class="eyebrow">Mentorship</span><h3>Learn with a mentor</h3><p>Find guidance for your goals.</p></a>
        </div></section>
        <section class="tab-panel" role="tabpanel" id="panel-community" aria-labelledby="tab-community" hidden><div class="grid">
            <a class="card" href="/portal/opportunities"><span class="eyebrow">Opportunities</span><h3>Find your next opportunity</h3><p>Explore work, training and market connections.</p></a>
            <a class="card" href="/portal/events"><span class="eyebrow">Events</span><h3>Connect and participate</h3><p>See upcoming events and manage your registrations.</p></a>
        </div></section>
    </div>
    @php $announcementsActive=count($data['course_progress'])===0; @endphp
    <h2>My learning progress</h2>
    <div class="tabs dashboard-updates" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Learning updates">
            <button type="button" role="tab" id="tab-dashboard-progress" aria-controls="panel-dashboard-progress" aria-selected="{{ $announcementsActive?'false':'true' }}" @if($announcementsActive)tabindex="-1"@endif>Learning progress <span class="tab-count">{{ count($data['course_progress']) }}</span></button>
            <button type="button" role="tab" id="tab-dashboard-announcements" aria-controls="panel-dashboard-announcements" aria-selected="{{ $announcementsActive?'true':'false' }}" @if(!$announcementsActive)tabindex="-1"@endif>Announcements <span class="tab-count">{{ count($data['announcements']) }}</span></button>
        </div>
        <section class="tab-panel" role="tabpanel" id="panel-dashboard-progress" aria-labelledby="tab-dashboard-progress" @if($announcementsActive)hidden @endif>
            @forelse($data['course_progress'] as $progressRow)<h3>{{ collect($data['courses'])->firstWhere('id',$progressRow['course_id'])?->title }}</h3>@include('portal.progress',['progressCourseId'=>$progressRow['course_id']])@empty<p class="muted">Enroll in a course to see your progress here.</p>@endforelse
        </section>
        <section class="tab-panel" role="tabpanel" id="panel-dashboard-announcements" aria-labelledby="tab-dashboard-announcements" @if(!$announcementsActive)hidden @endif>
            @forelse($data['announcements'] as $a)<article class="panel"><h3>{{ $a->title }}</h3><p class="prose">{{ $a->body }}</p></article>@empty<p class="muted">No announcements yet. Check back soon.</p>@endforelse
        </section>
    </div>
</div>
@endsection
