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
@if(in_array($type,['contacts','audit_logs']))
<div class="panel table-wrap review-table-wrap"><table class="data-table">
    <caption class="sr-only">{{ $type==='contacts'?'Enquiries':'Audit trail' }}</caption>
    @if($type==='contacts')
    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Enquiry</th><th scope="col">Received</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead>
    <tbody>@forelse($rows as $row)
        <tr>
            <td class="column-primary"><strong>{{ $row->name }}</strong><small>Enquiry #{{ $row->id }}</small></td>
            <td><a href="mailto:{{ $row->email }}">{{ $row->email }}</a></td>
            <td class="enquiry-message"><details><summary>{{ \Illuminate\Support\Str::limit($row->message,120) }}</summary><p>{{ $row->message }}</p></details></td>
            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i') }}</td>
            <td><span class="status-badge" data-status="{{ $row->status }}">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span></td>
            <td><form method="post" action="/admin/reviews/contacts/{{ $row->id }}" class="review-status-form">@csrf<label class="sr-only" for="contact-status-{{ $row->id }}">Status for enquiry {{ $row->id }}</label><select id="contact-status-{{ $row->id }}" name="status">@foreach(['new','in_progress','resolved'] as $status)<option value="{{ $status }}" @selected($row->status===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select><button class="small" type="submit">Save</button></form></td>
        </tr>
    @empty<tr><td colspan="6" class="table-empty">No enquiries found.</td></tr>@endforelse</tbody>
    @else
    <thead><tr><th scope="col">Date and time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Module</th><th scope="col">Record</th></tr></thead>
    <tbody>@forelse($rows as $row)
        <tr>
            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i:s') }}</td>
            <td>{{ isset($row->user_id)?(\App\Models\User::find($row->user_id)?->name??'Deleted user'):'System' }}</td>
            <td><span class="tag">{{ ucwords(str_replace(['-','_'],' ',$row->action)) }}</span></td>
            <td>{{ ucwords(str_replace('_',' ',$row->module)) }}</td>
            <td>{{ $row->record_id??'—' }}</td>
        </tr>
    @empty<tr><td colspan="5" class="table-empty">No audit entries found.</td></tr>@endforelse</tbody>
    @endif
</table></div>
@else
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
@endif
@if($rows->isEmpty()&&!in_array($type,['contacts','audit_logs']))<div class="empty">Nothing matches these filters. Try a different search or period.</div>@endif
@include('partials.pagination',['rows'=>$rows])
</div>
@endsection
