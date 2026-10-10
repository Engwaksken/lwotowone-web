@extends('layout')
@section('content')
<div class="page-wrap">
<span class="eyebrow"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Programme operations</span>
<h1 class="page-title">{{ ucwords(str_replace('_',' ',$type)) }}</h1>
@include('partials.platform-stats')
@php
    $reviewStatusList = match($type){'submissions'=>['submitted','passed','returned','failed'],'practice_logs'=>['pending','verified','returned'],'bookings'=>['requested','confirmed','completed','cancelled'],'applications'=>['submitted','reviewing','shortlisted','accepted','rejected'],'event_registrations'=>['registered','attended','absent','cancelled'],'contacts'=>['new','in_progress','resolved'],default=>[]};
    $reviewStatusOptions = $reviewStatusList
        ? collect($reviewStatusList)->mapWithKeys(fn($status)=>[$status=>ucfirst($status)])->prepend('All statuses','')->all()
        : [];
@endphp
<x-filter-bar
    :action="'/admin/reviews/'.$type"
    :search="$filters['q']"
    searchLabel="Search records"
    searchPlaceholder="Search"
    :statusOptions="$reviewStatusOptions"
    :status="$filters['status']"
    statusLabel="Status"
    :periodOptions="['all'=>'All time','week'=>'This week','month'=>'This month','year'=>'This year','custom'=>'Custom range']"
    :period="$filters['period']"
    periodLabel="Period"
    idPrefix="review"
>
    <div class="field"><label for="review-start">From</label><input id="review-start" type="date" name="start_date" value="{{ $filters['start_date'] }}"></div>
    <div class="field"><label for="review-end">To</label><input id="review-end" type="date" name="end_date" value="{{ $filters['end_date'] }}"></div>
</x-filter-bar>
<div class="review-list">
@foreach($rows as $row)
    <details class="review-item">
        <summary><span>#{{ $row->id }}</span><span class="tag">{{ $row->status??$row->action??'' }}</span>@if(isset($row->user_id))<span>{{ \App\Models\User::find($row->user_id)?->name }}</span>@endif</summary>
        <div class="data">
            @foreach((array)$row as $key=>$value)
                @if(!in_array($key,['file_path','updated_at']))@php $display=\App\Services\RecordPresentation::field(['label'=>\Illuminate\Support\Str::headline($key),'value'=>$value]); @endphp<p><strong>{{ $display['label'] }}:</strong> {{ $display['value']??'—' }}</p>@endif
            @endforeach
        </div>
        @if($type==='submissions'&&$row->file_path)<a href="/files/submissions/{{ $row->id }}"><i class="fas fa-paperclip" aria-hidden="true"></i> Download submitted evidence</a>@endif
        @if($type!=='audit_logs')
            <form method="post" action="/admin/reviews/{{ $type }}/{{ $row->id }}">@csrf
                <div class="field"><label>Status</label><select name="status">@foreach(match($type){'submissions'=>['passed','returned','failed'],'practice_logs'=>['verified','returned'],'bookings'=>['confirmed','completed','cancelled'],'applications'=>['reviewing','shortlisted','accepted','rejected'],'event_registrations'=>['registered','attended','absent','cancelled'],default=>['new','in_progress','resolved']} as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div>
                @if($type==='submissions')<div class="field"><label>Score (%)</label><input type="number" name="score" min="0" max="100" required></div>@endif
                @if(in_array($type,['submissions','practice_logs','applications','bookings']))<div class="field"><label>Feedback / session notes</label><textarea name="{{ $type==='bookings'?'notes':'feedback' }}" @required(in_array($type,['submissions','practice_logs']))></textarea></div>@endif
                <button><i class="fas fa-save" aria-hidden="true"></i> Save review</button>
            </form>
        @endif
    </details>
@endforeach
</div>
@if($rows->isEmpty())<div class="empty">Nothing matches these filters. Try a different search or period.</div>@endif
@include('partials.pagination',['rows'=>$rows])
</div>
@endsection
