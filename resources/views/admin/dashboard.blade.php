@extends('layout')
@section('content')
<span class="eyebrow">Management portal</span>
<h1>Skills. Enterprise. Impact.</h1>
<p>Manage practical learning and help participants take their next step.</p>
@if(auth()->user()->manager())
    <div class="stats">@foreach($counts as $label=>$count)<div class="stat"><span class="stat-icon"><i class="fas {{ match($label){'users'=>'fa-users','courses'=>'fa-graduation-cap','enrolments'=>'fa-user-check','submissions'=>'fa-clipboard-check','bookings'=>'fa-comments','applications'=>'fa-file-signature','enterprises'=>'fa-store',default=>'fa-chart-bar'} }}" aria-hidden="true"></i></span><strong>{{ number_format($count) }}</strong><span>{{ ucwords(str_replace('_',' ',$label)) }}</span></div>@endforeach</div>
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
                    @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-appearance"><i class="fas fa-palette" aria-hidden="true"></i><span><strong>Website appearance</strong><small>Set brand colors, typography, logo and favicon.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                    <li><a class="management-row" href="/admin/site-settings#payment-methods"><i class="fas fa-credit-card" aria-hidden="true"></i><span><strong>Payment gateway settings</strong><small>Manage payment destinations and learner instructions.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>
                    @if(auth()->user()->role==='admin')<li><a class="management-row" href="/admin/site-settings#settings-panel-ai"><i class="fas fa-robot" aria-hidden="true"></i><span><strong>AI API settings</strong><small>Configure chatbot responses and mentor recommendations.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                @else
                    @foreach($keys as $key)<li><a class="management-row" href="/admin/{{ $key }}"><i class="fas {{ match($key){'programs'=>'fa-layer-group','courses'=>'fa-graduation-cap','lessons'=>'fa-book-open','assignments'=>'fa-tasks','resources'=>'fa-folder-open','skills'=>'fa-tools','users'=>'fa-users','slots'=>'fa-calendar-check','opportunities'=>'fa-briefcase','events'=>'fa-calendar-alt','announcements'=>'fa-bullhorn','posts'=>'fa-newspaper','pages'=>'fa-file-alt',default=>'fa-folder'} }}" aria-hidden="true"></i><span><strong>{{ config("modules.$key.title") }}</strong><small>View and manage {{ \Illuminate\Support\Str::lower(config("modules.$key.title")) }} records.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endforeach
                    @if($label==='Learning and skills'&&auth()->user()->manager())<li><a class="management-row" href="/admin/enrollment"><i class="fas fa-user-graduate" aria-hidden="true"></i><span><strong>Enrollment and cohorts</strong><small>Manage cohorts, settlements and course access.</small></span><i class="fas fa-chevron-right management-arrow" aria-hidden="true"></i></a></li>@endif
                @endif
            </ul>
        </section>
    @endforeach
</div>
@endsection
