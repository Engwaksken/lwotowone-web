@extends('layout')
@section('title','Download requests')
@section('content')
<div class="page-wrap">
@php
    $statusLabels = ['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','revoked'=>'Withdrawn'];
    $icons = ['pending'=>'fa-hourglass-half','approved'=>'fa-check-circle','rejected'=>'fa-times-circle','revoked'=>'fa-ban'];
    $max = \App\Services\Downloads::MAX_DOWNLOADS;
@endphp
<span class="eyebrow">Learning and content</span>
<h1 class="page-title">Download requests</h1>
<p class="page-intro">Learners watch and read content inside the platform. To keep a copy of a lesson or resource file they send a request here. An approval allows {{ $max }} downloads within {{ \App\Services\Downloads::VALID_DAYS }} days.</p>

<section class="stats progress-stats" aria-label="Download request summary">
    @foreach($statusLabels as $status=>$label)
        <a class="stat" href="/admin/download-requests?status={{ $status }}" @if($filters['status']===$status) aria-current="true" @endif><span class="stat-icon"><i class="fas {{ $icons[$status] }}" aria-hidden="true"></i></span><strong>{{ number_format($counts[$status] ?? 0) }}</strong><span>{{ $label }}</span></a>
    @endforeach
</section>

<x-filter-bar
    :action="'/admin/download-requests'"
    searchName="q"
    :search="$filters['q']"
    searchLabel="Search learners"
    searchPlaceholder="Name or email"
    :statusOptions="['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','revoked'=>'Withdrawn','all'=>'All requests']"
    :status="$filters['status']"
    statusLabel="Status"
    idPrefix="downloads"
/>

<section class="panel">
    <div class="table-wrap"><table><thead><tr><th>Learner</th><th>Content</th><th>Reason given</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($rows as $row)
        @php
            $item = $items[$row->item_type]->get($row->item_id);
            $state = \App\Services\Downloads::state($row);
        @endphp
        <tr>
            <td>{{ $row->name }}<small>{{ $row->email }}@if($row->learner_no) · {{ $row->learner_no }}@endif</small></td>
            <td>@if($item){{ \App\Services\Downloads::title($item) }}<small>{{ \Illuminate\Support\Str::singular(ucfirst($row->item_type)) }} · {{ $courses[$item->course_id] ?? 'Course' }} · {{ ($item->content_language ?? 'en')==='lg'?'Luganda':'English' }}</small>@else<span class="muted">Deleted content</span>@endif</td>
            <td>{{ $row->message ? \Illuminate\Support\Str::limit($row->message,140) : '—' }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($row->updated_at)->format('d M Y') }}</td>
            <td>
                <span class="status-badge" data-status="{{ $row->status }}">{{ match($state){'approved'=>'Approved','used_up'=>'All downloads used','expired'=>'Expired',default=>$statusLabels[$row->status] ?? ucfirst($row->status)} }}</span>
                @if($row->status==='approved')<small>{{ $row->downloads_used }} of {{ $max }} used · until {{ \Illuminate\Support\Carbon::parse($row->expires_at)->format('d M Y') }}</small>@endif
                @if($row->decision_note)<small>{{ $row->decision_note }}</small>@endif
            </td>
            <td><div class="actions">
                @if($row->status==='pending')
                    @if($item)<button type="button" class="secondary small icon-action" data-dialog-open="preview-download-{{ $row->id }}" title="Preview content" aria-label="Preview the requested content"><i class="fas fa-play-circle" aria-hidden="true"></i></button>@endif
                    <form method="post" action="/admin/download-requests/{{ $row->id }}/approve">@csrf<button type="submit" class="small">Approve</button></form>
                    <button type="button" class="danger small" data-dialog-open="decline-download-{{ $row->id }}">Reject</button>
                @elseif($state==='approved')
                    <button type="button" class="danger small" data-dialog-open="decline-download-{{ $row->id }}">Withdraw</button>
                @endif
            </div>
            @if($row->status==='pending'||$state==='approved')
                @php $decision = $row->status==='pending' ? 'reject' : 'revoke'; @endphp
                <dialog class="form-dialog" id="decline-download-{{ $row->id }}"><div class="dialog-heading"><h2>{{ $decision==='reject'?'Reject':'Withdraw' }} {{ $row->name }}’s download</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div><form class="dialog-form" method="post" action="/admin/download-requests/{{ $row->id }}/{{ $decision }}">@csrf<div class="field"><label for="download-note-{{ $row->id }}">Reason shared with the learner (optional)</label><textarea id="download-note-{{ $row->id }}" name="decision_note" maxlength="1000"></textarea></div><div class="dialog-actions"><button class="danger">{{ $decision==='reject'?'Reject request':'Withdraw access' }}</button></div></form></dialog>
            @endif
            @if($row->status==='pending'&&$item)
                <dialog class="form-dialog content-preview-dialog" id="preview-download-{{ $row->id }}"><div class="dialog-heading"><h2>Requested: {{ \App\Services\Downloads::title($item) }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>@include('admin.content-body',['record'=>$item,'module'=>$row->item_type])</dialog>
            @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6">No download requests match these filters.</td></tr>
    @endforelse
    </tbody></table></div>
    @include('partials.pagination',['rows'=>$rows])
</section>
</div>
@endsection
