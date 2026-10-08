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
        'Opportunities and updates'=>['opportunities','events','announcements','posts','pages','settings'],
    ];
    $availableGroups=[];
    foreach($moduleGroups as $label=>$keys){
        $availableGroups[$label]=array_filter($keys,fn($key)=>isset(config('modules')[$key])&&\App\Services\Catalog::allowed(auth()->user(),$key));
    }
    $availableGroups=array_filter($availableGroups);
@endphp
<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Management areas">
        @foreach($availableGroups as $label=>$keys)<button type="button" role="tab" id="tab-management-{{ $loop->index }}" aria-controls="panel-management-{{ $loop->index }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $label }}</button>@endforeach
    </div>
    @foreach($availableGroups as $label=>$keys)
        <section class="tab-panel" role="tabpanel" id="panel-management-{{ $loop->index }}" aria-labelledby="tab-management-{{ $loop->index }}">
            <div class="grid">@foreach($keys as $key)<a class="card" href="/admin/{{ $key }}"><h3>{{ config("modules.$key.title") }}</h3><p>View and update records</p></a>@endforeach</div>
        </section>
    @endforeach
</div>
@endsection
