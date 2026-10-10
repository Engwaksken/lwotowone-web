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
    $activity = is_array($metrics['activity'] ?? null) ? array_slice(array_values($metrics['activity']), 0, 8) : [];

    $count = fn ($value) => is_numeric($value) ? (int) $value : 0;
    $percent = fn ($value) => number_format(is_numeric($value) ? min(100, max(0, (float) $value)) : 0, 1);
    $when = function ($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $attentionItems = [
        ['key' => 'learners_awaiting_payment', 'label' => 'Learners awaiting payment', 'href' => '/admin/enrollment', 'icon' => 'fa-clock'],
        ['key' => 'applications_to_review', 'label' => 'Applications to review', 'href' => '/admin/reviews/applications', 'icon' => 'fa-file-signature'],
        ['key' => 'submissions_to_review', 'label' => 'Submissions to review', 'href' => '/admin/reviews/submissions', 'icon' => 'fa-clipboard-check'],
        ['key' => 'practice_logs_pending', 'label' => 'Practice logs pending', 'href' => '/admin/reviews/practice_logs', 'icon' => 'fa-tasks'],
        ['key' => 'bookings_requested', 'label' => 'Mentorship requests', 'href' => '/admin/reviews/bookings', 'icon' => 'fa-comments'],
        ['key' => 'contacts_new', 'label' => 'New enquiries', 'href' => '/admin/reviews/contacts', 'icon' => 'fa-envelope'],
    ];

    $funnelSteps = [
        ['label' => 'Registered', 'value' => $count($funnel['registered'] ?? 0)],
        ['label' => 'Profile complete', 'value' => $count($funnel['profile_complete'] ?? 0)],
        ['label' => 'Paid', 'value' => $count($funnel['paid'] ?? 0)],
    ];
    $funnelMax = max(1, ...array_column($funnelSteps, 'value'));
@endphp
<div class="page-wrap">
    <span class="eyebrow">Management portal</span>
    <h1 class="page-title">Skills. Enterprise. Impact.</h1>
    <p class="page-intro">Today's work and programme progress.</p>

    <h2>Needs attention today</h2>
    <div class="stats">
        @foreach($attentionItems as $item)
            <a class="stat" href="{{ $item['href'] }}" data-stat-index="{{ $loop->index }}">
                <span class="stat-icon"><i class="fas {{ $item['icon'] }}" aria-hidden="true"></i></span>
                <strong>{{ number_format($count($attention[$item['key']] ?? 0)) }}</strong>
                <span>{{ $item['label'] }}</span>
                <span>Review <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
        @endforeach
    </div>

    @if($money !== null)
        <h2>Money</h2>
        <div class="stats">
            <div class="stat" data-stat-index="0">
                <span class="stat-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                <strong>{{ number_format($count($money['payments_this_month_count'] ?? 0)) }}</strong>
                <span>Payments this month</span>
            </div>
            @if(isset($money['payments_this_month_amount']) && is_numeric($money['payments_this_month_amount']))
                <div class="stat" data-stat-index="1">
                    <span class="stat-icon"><i class="fas fa-coins" aria-hidden="true"></i></span>
                    <strong>{{ number_format((float) $money['payments_this_month_amount'], 2) }}</strong>
                    <span>Payment value this month</span>
                </div>
            @endif
            <div class="stat" data-stat-index="2">
                <span class="stat-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></span>
                <strong>{{ number_format($count($money['trials_ending_7_days'] ?? 0)) }}</strong>
                <span>Trials ending in 7 days</span>
            </div>
        </div>
    @endif

    <h2>Growth and programmes</h2>
    <div class="stats">
        <div class="stat" data-stat-index="0">
            <span class="stat-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
            <strong>{{ number_format($count($growth['registrations_this_month'] ?? 0)) }}</strong>
            <span>Registrations this month</span>
        </div>
        <div class="stat" data-stat-index="1">
            <span class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
            <strong>{{ number_format($count($growth['participants_total'] ?? 0)) }}</strong>
            <span>Participants in total</span>
        </div>
        <div class="stat" data-stat-index="2">
            <span class="stat-icon"><i class="fas fa-id-card" aria-hidden="true"></i></span>
            <strong>{{ $percent($growth['profile_complete_rate'] ?? 0) }}%</strong>
            <span>Profiles complete</span>
        </div>
        <div class="stat" data-stat-index="3">
            <span class="stat-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
            <strong>{{ number_format($count($programmes['active_cohorts'] ?? 0)) }}</strong>
            <span>Active cohorts</span>
        </div>
        <div class="stat" data-stat-index="4">
            <span class="stat-icon"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
            <strong>{{ $percent($programmes['course_completion_rate'] ?? 0) }}%</strong>
            <span>Course completion</span>
        </div>
        <div class="stat" data-stat-index="5">
            <span class="stat-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
            <strong>{{ number_format($count($programmes['mel_learners'] ?? 0)) }}</strong>
            <span>MEL learners</span>
        </div>
    </div>

    <div class="mel-chart">
        <h2>Learner funnel</h2>
        <p>From first registration to confirmed payment.</p>
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
    </div>

    <h2>Events and calls</h2>
    <div class="stats">
        <div class="stat" data-stat-index="0">
            <span class="stat-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
            <strong>{{ number_format($count($events['open_calls'] ?? 0)) }}</strong>
            <span>Open calls</span>
            <span><a href="/admin/calls">Manage calls</a></span>
        </div>
    </div>
    <section class="panel" aria-labelledby="dash-upcoming">
        <div class="mel-panel-heading">
            <div>
                <h3 id="dash-upcoming">Upcoming events</h3>
                <p>The next five events and who has registered.</p>
            </div>
            <a class="button secondary small" href="/admin/events">Manage events</a>
        </div>
        @if($upcomingEvents)
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Event</th><th>Date</th><th>Registered</th></tr></thead>
                    <tbody>
                        @foreach($upcomingEvents as $event)
                            @php
                                $event = (array) $event;
                                $registered = $count($event['registered'] ?? 0);
                                $capacity = $event['capacity'] ?? null;
                                $hasCapacity = is_numeric($capacity) && (int) $capacity > 0;
                            @endphp
                            <tr>
                                <td>{{ $event['title'] ?? 'Untitled event' }}</td>
                                <td>{{ $when($event['starts_at'] ?? null) }}</td>
                                <td>
                                    @if($hasCapacity)
                                        <span>{{ $registered }} of {{ (int) $capacity }}</span>
                                        <div class="mel-bar-track" role="img" aria-label="{{ $registered }} of {{ (int) $capacity }} registered">
                                            <div class="mel-bar-fill" style="width:{{ min(100, round($registered * 100 / (int) $capacity)) }}%"></div>
                                        </div>
                                    @else
                                        {{ $registered }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">No upcoming events. Add an event to show it here.</div>
        @endif
    </section>

    <h2>Recent activity</h2>
    @if($activity)
        <ul class="management-list">
            @foreach($activity as $entry)
                @php $entry = (array) $entry; @endphp
                <li>
                    <div class="management-row">
                        <span>
                            <strong>{{ ucfirst((string) ($entry['action'] ?? 'Activity')) }}</strong>
                            <small>{{ $entry['actor'] ?? 'System' }} · {{ $when($entry['at'] ?? null) }}</small>
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="empty">No recent activity yet. Changes made by your team will appear here.</div>
    @endif

    <h2>Manage your work</h2>
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
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Management areas">
            @foreach($availableGroups as $label=>$keys)<button type="button" role="tab" id="tab-management-{{ $loop->index }}" aria-controls="panel-management-{{ $loop->index }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $label }}</button>@endforeach
        </div>
        @foreach($availableGroups as $label=>$keys)
            <section class="tab-panel" role="tabpanel" id="panel-management-{{ $loop->index }}" aria-labelledby="tab-management-{{ $loop->index }}">
                <ul class="management-list">
                    @if($label==='Settings')
                        @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-appearance"><i class="fas fa-palette" aria-hidden="true"></i><span><strong>Website appearance</strong><small>Set brand colours, typography, logo and favicon.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                        <li><a class="management-row" href="/admin/site-settings#payment-methods"><i class="fas fa-credit-card" aria-hidden="true"></i><span><strong>Payment gateway settings</strong><small>Set up payment options and learner instructions.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>
                        @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-ai"><i class="fas fa-robot" aria-hidden="true"></i><span><strong>AI API settings</strong><small>Set up chatbot replies and mentor suggestions.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                    @else
                        @foreach($keys as $key)<li><a class="management-row" href="/admin/{{ $key }}"><i class="fas {{ match($key){'programs'=>'fa-layer-group','courses'=>'fa-graduation-cap','lessons'=>'fa-book-open','assignments'=>'fa-tasks','resources'=>'fa-folder-open','skills'=>'fa-tools','users'=>'fa-users','slots'=>'fa-calendar-check','opportunities'=>'fa-briefcase','events'=>'fa-calendar-alt','announcements'=>'fa-bullhorn','posts'=>'fa-newspaper','pages'=>'fa-file-alt',default=>'fa-folder'} }}" aria-hidden="true"></i><span><strong>{{ config("modules.$key.title") }}</strong><small>View and edit {{ \Illuminate\Support\Str::lower(config("modules.$key.title")) }}.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endforeach
                        @if($label==='Learning and skills'&&auth()->user()->manager())<li><a class="management-row" href="/admin/enrollment"><i class="fas fa-user-graduate" aria-hidden="true"></i><span><strong>Enrollment and cohorts</strong><small>Manage cohorts, settlements and course access.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                    @endif
                </ul>
            </section>
        @endforeach
    </div>
</div>
@endsection
