@extends('layout')
@section('content')
<div class="page-wrap">
<span class="eyebrow"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Programme operations</span>
<h1 class="page-title">{{ ucwords(str_replace('_',' ',$type)) }}</h1>
@include('partials.platform-stats')
<form class="panel filter-form" method="get">
    <div class="field search-field"><label for="review-search">Search records</label><input id="review-search" type="search" name="q" maxlength="255" value="{{ $filters['q'] }}" placeholder="Search"></div>
    @if($type!=='audit_logs')
        <div class="field"><label for="review-status">Status</label><select id="review-status" name="status"><option value="">All statuses</option>@foreach(match($type){'submissions'=>['submitted','passed','returned','failed'],'practice_logs'=>['pending','verified','returned'],'bookings'=>['requested','confirmed','completed','cancelled'],'applications'=>['submitted','reviewing','shortlisted','accepted','rejected'],'event_registrations'=>['registered','attended','cancelled'],'contacts'=>['new','in_progress','resolved'],default=>[]} as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label for="review-period">Period</label><select id="review-period" name="period">@foreach(['all'=>'All time','week'=>'This week','month'=>'This month','year'=>'This year','custom'=>'Custom range'] as $value=>$label)<option value="{{ $value }}" @selected($filters['period']===$value)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="review-start">From</label><input id="review-start" type="date" name="start_date" value="{{ $filters['start_date'] }}"></div>
    <div class="field"><label for="review-end">To</label><input id="review-end" type="date" name="end_date" value="{{ $filters['end_date'] }}"></div>
    <div class="filter-actions"><button><i class="fas fa-search" aria-hidden="true"></i> Apply</button> <a href="/admin/reviews/{{ $type }}">Reset</a></div>
</form>
<div class="review-list">
@foreach($rows as $row)
    <details class="review-item">
        <summary><span>#{{ $row->id }}</span><span class="tag">{{ $row->status??$row->action??'' }}</span>@if(isset($row->user_id))<span>{{ \App\Models\User::find($row->user_id)?->name }}</span>@endif</summary>
        <div class="data">
            @foreach((array)$row as $key=>$value)
                @if(!in_array($key,['file_path','updated_at']))<p><strong>{{ ucwords(str_replace('_',' ',$key)) }}:</strong> {{ $value }}</p>@endif
            @endforeach
        </div>
        @if($type==='submissions'&&$row->file_path)<a href="/files/submissions/{{ $row->id }}"><i class="fas fa-paperclip" aria-hidden="true"></i> Download submitted evidence</a>@endif
        @if($type!=='audit_logs')
            <form method="post" action="/admin/reviews/{{ $type }}/{{ $row->id }}">@csrf
                <div class="field"><label>Status</label><select name="status">@foreach(match($type){'submissions'=>['passed','returned','failed'],'practice_logs'=>['verified','returned'],'bookings'=>['confirmed','completed','cancelled'],'applications'=>['reviewing','shortlisted','accepted','rejected'],'event_registrations'=>['registered','attended','cancelled'],default=>['new','in_progress','resolved']} as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div>
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
