@extends('layout')
@section('content')
@php
    $metrics = is_array($metrics ?? null) ? $metrics : [];
    $attention = is_array($metrics['attention'] ?? null) ? $metrics['attention'] : [];
    $money = is_array($metrics['money'] ?? null) ? $metrics['money'] : null;
    $growth = is_array($metrics['growth'] ?? null) ? $metrics['growth'] : [];
    $funnel = is_array($growth['funnel'] ?? null) ? $growth['funnel'] : [];
    $programmes = is_array($metrics['programmes'] ?? null) ? $metrics['programmes'] : [];
    $events = is_array($metrics['events'] ?? null) ? $metrics['events'] : [];
    $upcomingEvents = is_array($events['upcoming'] ?? null) ? array_slice(array_values($events['upcoming']), 0, 5) : [];
    $activity = is_array($metrics['activity'] ?? null) ? array_slice(array_values($metrics['activity']), 0, 5) : [];

    $count = fn ($value) => is_numeric($value) ? (int) $value : 0;
    $percent = fn ($value) => number_format(is_numeric($value) ? min(100, max(0, (float) $value)) : 0, 1);
    $when = function ($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M, H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $attentionItems = [
        ['key' => 'learners_awaiting_payment', 'label' => 'Awaiting payment', 'href' => '/admin/enrollment', 'icon' => 'fa-clock'],
        ['key' => 'applications_to_review', 'label' => 'Applications', 'href' => '/admin/reviews/applications', 'icon' => 'fa-file-signature'],
        ['key' => 'submissions_to_review', 'label' => 'Submissions', 'href' => '/admin/reviews/submissions', 'icon' => 'fa-clipboard-check'],
        ['key' => 'practice_logs_pending', 'label' => 'Practice logs', 'href' => '/admin/reviews/practice_logs', 'icon' => 'fa-tasks'],
        ['key' => 'bookings_requested', 'label' => 'Mentorship', 'href' => '/admin/reviews/bookings', 'icon' => 'fa-comments'],
        ['key' => 'contacts_new', 'label' => 'Enquiries', 'href' => '/admin/reviews/contacts', 'icon' => 'fa-envelope'],
    ];

    $funnelSteps = [
        ['label' => 'Registered', 'value' => $count($funnel['registered'] ?? 0)],
        ['label' => 'Profile complete', 'value' => $count($funnel['profile_complete'] ?? 0)],
        ['label' => 'Paid', 'value' => $count($funnel['paid'] ?? 0)],
    ];
    $funnelMax = max(1, ...array_column($funnelSteps, 'value'));

    $tiles = [];
    if ($money !== null) {
        $tiles[] = ['label' => 'Payments this month', 'value' => number_format($count($money['payments_this_month_count'] ?? 0)), 'icon' => 'fa-receipt'];
        if (isset($money['payments_this_month_amount']) && is_numeric($money['payments_this_month_amount'])) {
            $tiles[] = ['label' => 'Payment value', 'value' => number_format((float) $money['payments_this_month_amount'], 2), 'icon' => 'fa-coins'];
        }
        $tiles[] = ['label' => 'Trials ending in 7 days', 'value' => number_format($count($money['trials_ending_7_days'] ?? 0)), 'icon' => 'fa-hourglass-half'];
    }
    $tiles[] = ['label' => 'Registrations this month', 'value' => number_format($count($growth['registrations_this_month'] ?? 0)), 'icon' => 'fa-user-plus'];
    $tiles[] = ['label' => 'Participants', 'value' => number_format($count($growth['participants_total'] ?? 0)), 'icon' => 'fa-users'];
    $tiles[] = ['label' => 'Profiles complete', 'value' => $percent($growth['profile_complete_rate'] ?? 0).'%', 'icon' => 'fa-id-card'];
    $tiles[] = ['label' => 'Active cohorts', 'value' => number_format($count($programmes['active_cohorts'] ?? 0)), 'icon' => 'fa-layer-group'];
    $tiles[] = ['label' => 'Course completion', 'value' => $percent($programmes['course_completion_rate'] ?? 0).'%', 'icon' => 'fa-graduation-cap'];
    $tiles[] = ['label' => 'MEL learners', 'value' => number_format($count($programmes['mel_learners'] ?? 0)), 'icon' => 'fa-chart-line'];
@endphp
<div class="page-wrap dash">
    <div class="dash-head">
        <div>
            <span class="eyebrow">Management portal</span>
            <h1 class="page-title">Today at a glance</h1>
        </div>
        <div class="dash-head-links">
            <a class="button secondary small" href="/admin/reviews/applications">Review queue</a>
            <a class="button small" href="/admin/events">Manage events</a>
        </div>
    </div>

    <nav class="dash-attention" aria-label="Needs attention">
        @foreach($attentionItems as $item)
            <a class="dash-chip" href="{{ $item['href'] }}">
                <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                <strong>{{ number_format($count($attention[$item['key']] ?? 0)) }}</strong>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="dash-grid">
        <section class="panel dash-card" aria-labelledby="dash-numbers">
            <h2 id="dash-numbers" class="dash-card-title">Key numbers</h2>
            <div class="dash-tiles">
                @foreach($tiles as $tile)
                    <div class="dash-tile">
                        <i class="fas {{ $tile['icon'] }}" aria-hidden="true"></i>
                        <strong>{{ $tile['value'] }}</strong>
                        <span>{{ $tile['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel dash-card" aria-labelledby="dash-funnel">
            <h2 id="dash-funnel" class="dash-card-title">Learner funnel</h2>
            <div class="mel-bar-list">
                @foreach($funnelSteps as $step)
                    <div class="mel-bar-row">
                        <span>{{ $step['label'] }}</span>
                        <div class="mel-bar-track" role="img" aria-label="{{ $step['label'] }}: {{ number_format($step['value']) }}">
                            <div class="mel-bar-fill" style="width:{{ round($step['value'] * 100 / $funnelMax) }}%"></div>
                        </div>
                        <strong>{{ number_format($step['value']) }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="dash-note"><i class="fas fa-bullhorn" aria-hidden="true"></i> {{ number_format($count($events['open_calls'] ?? 0)) }} open calls · <a href="/admin/calls">Manage</a></p>
        </section>

        <section class="panel dash-card" aria-labelledby="dash-events">
            <div class="dash-card-head">
                <h2 id="dash-events" class="dash-card-title">Upcoming events</h2>
                <a href="/admin/events">All events</a>
            </div>
            @if($upcomingEvents)
                <ul class="dash-list">
                    @foreach($upcomingEvents as $event)
                        @php
                            $event = (array) $event;
                            $registered = $count($event['registered'] ?? 0);
                            $capacity = $event['capacity'] ?? null;
                            $hasCapacity = is_numeric($capacity) && (int) $capacity > 0;
                        @endphp
                        <li>
                            <span>
                                <strong>{{ $event['title'] ?? 'Untitled event' }}</strong>
                                <small>{{ $when($event['starts_at'] ?? null) }}</small>
                            </span>
                            <span class="dash-count">{{ $registered }}@if($hasCapacity)<small>/{{ (int) $capacity }}</small>@endif</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="dash-empty">No upcoming events.</p>
            @endif
        </section>

        <section class="panel dash-card" aria-labelledby="dash-activity">
            <h2 id="dash-activity" class="dash-card-title">Recent activity</h2>
            @if($activity)
                <ul class="dash-list">
                    @foreach($activity as $entry)
                        @php $entry = (array) $entry; @endphp
                        <li>
                            <span>
                                <strong>{{ ucfirst((string) ($entry['action'] ?? 'Activity')) }}</strong>
                                <small>{{ $entry['actor'] ?? 'System' }} · {{ $when($entry['at'] ?? null) }}</small>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="dash-empty">Changes made by your team will appear here.</p>
            @endif
        </section>
    </div>

    @php
        $moduleGroups=[
            'Learning and skills'=>['programs','courses','lessons','assignments','resources','skills'],
            'People and support'=>['users','mentors','slots'],
            'Opportunities and updates'=>['opportunities','events','announcements','posts','pages'],
        ];
        $availableGroups=[];
        foreach($moduleGroups as $label=>$keys){
            $availableGroups[$label]=array_filter($keys,fn($key)=>isset(config('modules')[$key])&&\App\Services\Catalog::allowed(auth()->user(),$key));
        }
        $availableGroups=array_filter($availableGroups);
        if(auth()->user()->manager())$availableGroups['Settings']=['site_appearance','payment_gateways'];
    @endphp
    <details class="dash-manage">
        <summary>Manage your work <span>{{ count($availableGroups) }} areas</span></summary>
        <div class="tabs dash-tabs" data-tabs>
            <div class="tab-list" role="tablist" aria-label="Management areas">
                @foreach($availableGroups as $label=>$keys)<button type="button" role="tab" id="tab-management-{{ $loop->index }}" aria-controls="panel-management-{{ $loop->index }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $label }}</button>@endforeach
            </div>
            @foreach($availableGroups as $label=>$keys)
                <section class="tab-panel" role="tabpanel" id="panel-management-{{ $loop->index }}" aria-labelledby="tab-management-{{ $loop->index }}">
                    <ul class="management-list dash-links">
                        @if($label==='Settings')
                            @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-appearance"><i class="fas fa-palette" aria-hidden="true"></i><span><strong>Website appearance</strong><small>Brand colours, typography, logo and favicon.</small></span></a></li>@endif
                            <li><a class="management-row" href="/admin/site-settings#payment-methods"><i class="fas fa-credit-card" aria-hidden="true"></i><span><strong>Payment gateway settings</strong><small>Payment options and learner instructions.</small></span></a></li>
                            @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-ai"><i class="fas fa-robot" aria-hidden="true"></i><span><strong>AI API settings</strong><small>Chatbot replies and mentor suggestions.</small></span></a></li>@endif
                        @else
                            @foreach($keys as $key)<li><a class="management-row" href="/admin/{{ $key }}"><i class="fas {{ match($key){'programs'=>'fa-layer-group','courses'=>'fa-graduation-cap','lessons'=>'fa-book-open','assignments'=>'fa-tasks','resources'=>'fa-folder-open','skills'=>'fa-tools','users'=>'fa-users','slots'=>'fa-calendar-check','opportunities'=>'fa-briefcase','events'=>'fa-calendar-alt','announcements'=>'fa-bullhorn','posts'=>'fa-newspaper','pages'=>'fa-file-alt',default=>'fa-folder'} }}" aria-hidden="true"></i><span><strong>{{ config("modules.$key.title") }}</strong><small>View and edit {{ \Illuminate\Support\Str::lower(config("modules.$key.title")) }}.</small></span></a></li>@endforeach
                            @if($label==='Learning and skills'&&auth()->user()->manager())<li><a class="management-row" href="/admin/enrollment"><i class="fas fa-user-graduate" aria-hidden="true"></i><span><strong>Enrollment and cohorts</strong><small>Cohorts, settlements and course access.</small></span></a></li>@endif
                        @endif
                    </ul>
                </section>
            @endforeach
        </div>
    </details>
</div>
@endsection
