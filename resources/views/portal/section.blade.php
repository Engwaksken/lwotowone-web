@extends('layout') @section('content')@php
    $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
    $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
    $attributesOf = fn ($row) => is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
    $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
        ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
        ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
        ->values()->all();
@endphp<div class="page-wrap"><span class="eyebrow">Your learning journey</span><h1 class="page-title">{{ match($section){'learn'=>'My learning','practice'=>'Practical skills','mentorship'=>'Mentorship','enterprise'=>'Enterprise and earnings',default=>ucfirst($section)} }}</h1>
@if(auth()->user()->full_access_until && auth()->user()->full_access_until->isFuture() && ! auth()->user()->learning_access_paid)
    <div class="notice" role="status">You have full access until <strong>{{ auth()->user()->full_access_until->format('d M Y, H:i') }}</strong>. <a href="/portal/payment">Confirm payment</a> to keep access after that.</div>
@endif
@if($section==='learn')
@php $enrolledCourses=collect($data['courses'])->filter(fn($c)=>collect($data['enrolments'])->contains('course_id',$c->id));$availableCourses=collect($data['courses'])->reject(fn($c)=>collect($data['enrolments'])->contains('course_id',$c->id)); @endphp
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Courses">
        <button type="button" role="tab" id="tab-my-courses" aria-controls="panel-my-courses" aria-selected="true">My courses <span class="tab-count">{{ $enrolledCourses->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-catalog" aria-controls="panel-course-catalog" aria-selected="false" tabindex="-1">Browse courses <span class="tab-count">{{ $availableCourses->count() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-my-courses" aria-labelledby="tab-my-courses">
        <div class="grid">@forelse($enrolledCourses as $c)<article class="card"><span class="tag">{{ $c->level }} · {{ $c->duration_hours }} hours</span><h3>{{ $c->title }}</h3><p>{{ $c->summary }}</p>@include('portal.progress',['progressCourseId'=>$c->id])<a class="button" href="/learning/{{ $c->id }}">Continue learning</a></article>@empty<div class="empty">You are not enrolled in any course yet. Browse courses to get started.</div>@endforelse</div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-catalog" aria-labelledby="tab-course-catalog">
        <div class="grid">@forelse($availableCourses as $c)<article class="card"><span class="tag">{{ $c->level }} · {{ $c->duration_hours }} hours</span><h3>{{ $c->title }}</h3><p>{{ $c->summary }}</p><a class="button" href="/calls">Find an open call</a></article>@empty<div class="empty">You are enrolled in all available courses.</div>@endforelse</div>
    </section>
</div>
@elseif($section==='practice')
@php
    $logsActive=request()->filled('q')||request()->query('period','all')!=='all'||request()->filled('start_date')||request()->filled('end_date');
    $verifiedLogs=collect($data['practice_logs'])->where('status','verified')->count();
    $totalMinutes=collect($data['practice_logs'])->sum('minutes');
@endphp
<div class="practice-summary">
    <article class="stat"><span class="stat-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></span><strong>{{ count($data['practice_logs']) }}</strong><span>Activities recorded</span></article>
    <article class="stat"><span class="stat-icon"><i class="fas fa-check-circle" aria-hidden="true"></i></span><strong>{{ $verifiedLogs }}</strong><span>Verified activities</span></article>
    <article class="stat"><span class="stat-icon"><i class="fas fa-clock" aria-hidden="true"></i></span><strong>{{ number_format($totalMinutes) }}</strong><span>Minutes practised</span></article>
</div>
<p class="page-intro">Log what you practised and what you learned. Staff can verify your entries.</p>
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Practical skills">
        <button type="button" role="tab" id="tab-record-practice" aria-controls="panel-record-practice" aria-selected="{{ $logsActive?'false':'true' }}" @if($logsActive)tabindex="-1"@endif><i class="fas fa-plus-circle" aria-hidden="true"></i> Log activity</button>
        <button type="button" role="tab" id="tab-skill-catalog" aria-controls="panel-skill-catalog" aria-selected="false" tabindex="-1"><i class="fas fa-tools" aria-hidden="true"></i> Skills catalogue <span class="tab-count">{{ count($data['skills']) }}</span></button>
        <button type="button" role="tab" id="tab-practice-history" aria-controls="panel-practice-history" aria-selected="{{ $logsActive?'true':'false' }}" @if(!$logsActive)tabindex="-1"@endif><i class="fas fa-history" aria-hidden="true"></i> My activity <span class="tab-count">{{ $practiceLogs->total() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-record-practice" aria-labelledby="tab-record-practice" @if($logsActive)hidden @endif>
        <form class="panel practice-form" method="post" action="/actions/practice">@csrf
            <div class="field"><label for="practice-skill">Skill</label><select id="practice-skill" name="skill_id" required>@foreach($data['skills'] as $skill)<option value="{{ $skill->id }}">{{ $skill->title }}</option>@endforeach</select></div>
            <div class="field"><label for="practice-title">What did you practise?</label><input id="practice-title" type="text" name="title" maxlength="255" required></div>
            <div class="field"><label for="practice-minutes">Minutes spent</label><input id="practice-minutes" type="number" name="minutes" min="1" max="1440" required></div>
            <div class="field"><label for="practice-date">Date</label><input id="practice-date" type="date" name="practised_on" max="{{ today()->toDateString() }}" required></div>
            <div class="field practice-description"><label for="practice-body">Describe your activity and learning</label><textarea id="practice-body" name="body" maxlength="10000" required></textarea></div>
            <div class="practice-submit"><button><i class="fas fa-save" aria-hidden="true"></i> Save log</button></div>
        </form>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-skill-catalog" aria-labelledby="tab-skill-catalog" hidden>
        <div class="grid">@forelse($data['skills'] as $skill)<article class="card skill-card"><span class="skill-icon"><i class="fas fa-award" aria-hidden="true"></i></span><div><h3>{{ $skill->title }}</h3><span class="tag">{{ $skill->category }}</span><p>{{ $skill->description }}</p></div></article>@empty<div class="empty">The skills catalogue is being prepared.</div>@endforelse</div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-practice-history" aria-labelledby="tab-practice-history">
        <x-filter-bar
            :action="'/portal/practice'"
            :search="$practiceFilters['q']"
            searchLabel="Search activities"
            searchPlaceholder="Skill, activity or description"
            :periodOptions="['all'=>'All time','week'=>'This week','month'=>'This month','year'=>'This year','custom'=>'Custom range']"
            :period="$practiceFilters['period']"
            periodLabel="Period"
            idPrefix="practice"
        >
            <div class="field"><label for="practice-start">From</label><input id="practice-start" type="date" name="start_date" value="{{ $practiceFilters['start_date'] }}"></div>
            <div class="field"><label for="practice-end">To</label><input id="practice-end" type="date" name="end_date" value="{{ $practiceFilters['end_date'] }}"></div>
        </x-filter-bar>
        <div class="panel table-wrap"><table>
            <thead><tr><th scope="col"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Date</th><th scope="col"><i class="fas fa-award" aria-hidden="true"></i> Skill</th><th scope="col"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Activity</th><th scope="col"><i class="fas fa-clock" aria-hidden="true"></i> Minutes</th><th scope="col"><i class="fas fa-check-circle" aria-hidden="true"></i> Status</th><th scope="col"><i class="fas fa-comment-dots" aria-hidden="true"></i> Feedback</th><th scope="col">Actions</th></tr></thead>
            <tbody>@forelse($practiceLogs as $log)<tr><td>{{ $log->practised_on }}</td><td>{{ $log->skill_title }}</td><td><strong>{{ $log->title }}</strong><div class="muted">{{ \Illuminate\Support\Str::limit($log->body,120) }}</div></td><td>{{ $log->minutes }}</td><td><span class="tag">{{ ucfirst($log->status) }}</span></td><td>{{ $log->feedback }}</td><td><x-record-view-trigger :dialogId="'view-practice-'.$log->id" :name="$log->title" /><x-record-view-dialog :id="'view-practice-'.$log->id" :title="$log->title" :fields="array_merge([['label'=>'Date','value'=>$log->practised_on],['label'=>'Skill','value'=>$log->skill_title],['label'=>'Activity','value'=>$log->title],['label'=>'Description','value'=>$log->body],['label'=>'Minutes','value'=>$log->minutes],['label'=>'Status','value'=>ucfirst((string) $log->status)],['label'=>'Feedback','value'=>$log->feedback]], $moreDetails($attributesOf($log), ['practised_on','skill_title','title','body','minutes','status','feedback']))" /></td></tr>@empty<tr><td colspan="7">No activities match these filters.</td></tr>@endforelse</tbody>
        </table>@include('partials.pagination',['rows'=>$practiceLogs])</div>
    </section>
</div>
@elseif($section==='mentorship')
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Mentorship">
        <button type="button" role="tab" id="tab-mentors" aria-controls="panel-mentors" aria-selected="true">Mentors <span class="tab-count">{{ count($data['mentors']) }}</span></button>
        <button type="button" role="tab" id="tab-sessions" aria-controls="panel-sessions" aria-selected="false" tabindex="-1">Available sessions <span class="tab-count">{{ count($data['slots']) }}</span></button>
        <button type="button" role="tab" id="tab-requests" aria-controls="panel-requests" aria-selected="false" tabindex="-1">My requests <span class="tab-count">{{ count($data['bookings']) }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-mentors" aria-labelledby="tab-mentors"><div class="grid">@forelse($data['mentors'] as $m)<article class="card mentor-match-card">@if($loop->first)<span class="tag"><i class="fas fa-star" aria-hidden="true"></i> Suggested for you</span>@endif<h3>{{ $m->name }}</h3><span class="tag">{{ $m->expertise }}</span><p>{{ $m->bio }}</p><p class="mentor-match-reason">{{ $m->match_reason??'See whether their experience fits your goals.' }}</p></article>@empty<div class="empty">Mentor profiles will show up here soon.</div>@endforelse</div></section>
    <section class="tab-panel" role="tabpanel" id="panel-sessions" aria-labelledby="tab-sessions">@forelse($data['slots'] as $s)<details><summary>{{ $s->title }} · {{ $s->starts_at }} · {{ $s->mode }}</summary><p>{{ $s->location }} · {{ collect($data['mentors'])->firstWhere('id',$s->mentor_id)?->name }}</p><form method="post" action="/actions/book">@csrf<input type="hidden" name="slot_id" value="{{ $s->id }}"><div class="field"><label>What would you like help with?</label><textarea name="goal" required></textarea></div><button>Request session</button></form></details>@empty<p class="muted">No available sessions. Check back soon.</p>@endforelse</section>
    <section class="tab-panel" role="tabpanel" id="panel-requests" aria-labelledby="tab-requests">@forelse($data['bookings'] as $b)<article class="panel"><span class="tag">{{ $b->status }}</span><h3>{{ $b->session_title }}</h3><small>{{ $b->starts_at }} · {{ $b->mentor_name }} · {{ $b->mode }}</small><p>{{ $b->location }}</p><p>{{ $b->goal }}</p><p>{{ $b->notes }}</p>@if(in_array($b->status,['requested','confirmed']))<form method="post" action="/actions/cancel-booking" data-confirm="Cancel your mentorship request?">@csrf<input type="hidden" name="booking_id" value="{{ $b->id }}"><button class="secondary">Cancel request</button></form>@endif</article>@empty<p class="muted">Your requests will show up here.</p>@endforelse</section>
</div>
@elseif($section==='opportunities')
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Opportunities">
        <button type="button" role="tab" id="tab-open-opportunities" aria-controls="panel-open-opportunities" aria-selected="true">Open opportunities <span class="tab-count">{{ count($data['opportunities']) }}</span></button>
        <button type="button" role="tab" id="tab-applications" aria-controls="panel-applications" aria-selected="false" tabindex="-1">My applications <span class="tab-count">{{ count($data['applications']) }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-open-opportunities" aria-labelledby="tab-open-opportunities">@forelse($data['opportunities'] as $o)<details><summary>{{ $o->title }} · {{ $o->type }}</summary><p>{{ $o->organisation }} · {{ $o->location }} · Apply by {{ $o->deadline }}</p><p class="prose">{{ $o->description }}</p>@php $app=collect($data['applications'])->firstWhere('opportunity_id',$o->id); @endphp @if($app)<p><strong>Application: {{ $app->status }}</strong> {{ $app->feedback }}</p>@else<form method="post" action="/actions/apply">@csrf<input type="hidden" name="opportunity_id" value="{{ $o->id }}"><div class="field"><label>Tell us why you're interested and what skills you bring</label><textarea name="motivation" required></textarea></div><button>Submit application</button></form>@endif</details>@empty<div class="empty">No open opportunities right now. Check back soon.</div>@endforelse</section>
    <section class="tab-panel" role="tabpanel" id="panel-applications" aria-labelledby="tab-applications">@forelse($data['applications'] as $a)<article class="panel"><strong>Opportunity #{{ $a->opportunity_id }}</strong><p>{{ $a->status }} · {{ $a->feedback }}</p></article>@empty<p class="muted">Applications you submit will appear here.</p>@endforelse</section>
</div>
@elseif($section==='enterprise')
@include('portal.enterprise')
@elseif($section==='events')@include('portal.events')
@elseif($section==='notifications')
@php $unreadNotifications=collect($data['notifications'])->whereNull('read_at');$readNotifications=collect($data['notifications'])->whereNotNull('read_at'); @endphp
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Notifications">
        <button type="button" role="tab" id="tab-unread-notifications" aria-controls="panel-unread-notifications" aria-selected="true">Unread <span class="tab-count">{{ $unreadNotifications->count() }}</span></button>
        <button type="button" role="tab" id="tab-read-notifications" aria-controls="panel-read-notifications" aria-selected="false" tabindex="-1">Earlier notifications <span class="tab-count">{{ $readNotifications->count() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-unread-notifications" aria-labelledby="tab-unread-notifications">
        @forelse($unreadNotifications as $notification)<article class="panel notification-card"><small>{{ $notification->created_at }} · Unread</small><h3>{{ $notification->data['title']??'Notification' }}</h3><p>{{ $notification->data['body']??'' }}</p><form method="post" action="/notifications/{{ $notification->id }}/read">@csrf<button class="secondary">Mark as read</button></form></article>@empty<div class="empty">You’re all caught up.</div>@endforelse
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-read-notifications" aria-labelledby="tab-read-notifications" hidden>
        @forelse($readNotifications as $notification)<article class="panel notification-card"><small>{{ $notification->created_at }} · Read</small><h3>{{ $notification->data['title']??'Notification' }}</h3><p>{{ $notification->data['body']??'' }}</p></article>@empty<p class="muted">Read notifications will appear here.</p>@endforelse
    </section>
</div>
@endif</div> @endsection
