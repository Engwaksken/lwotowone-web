@extends('layout')
@section('content')
<div class="page-wrap">
<span class="eyebrow"><i class="fas fa-folder-open" aria-hidden="true"></i> Content management</span>
<h1 class="page-title">{{ $meta['title'] }}</h1>
@if($participantsOnly ?? false)<p class="page-intro">Manage participant accounts, enrollment, and course access.</p>@endif
@include('partials.platform-stats')
@php
    $cmsStatusOptions = $statusOptions
        ? collect($statusOptions)->mapWithKeys(fn($status)=>[$status=>ucfirst($status)])->prepend('All statuses','all')->all()
        : [];
    $tableColumns=match($module){
        'courses'=>['program_id'=>'Program Name','instructors'=>'Instructor Name','learner_count'=>'Learners'],
        'programs'=>['category'=>'Category'],
        'lessons'=>['course_id'=>'Course Name','content_language'=>'Language','content_format'=>'Format','position'=>'Order'],
        'course_modules'=>['course_id'=>'Course Name','position'=>'Order'],
        'resources'=>['course_id'=>'Course Name','content_language'=>'Language','content_format'=>'Format'],
        'assignments'=>['course_id'=>'Course Name','content_language'=>'Language','pass_mark'=>'Pass mark'],
        'users'=>['email'=>'Email','role'=>'Role'],
        'slots'=>['mentor_id'=>'Mentor Name','starts_at'=>'Starts'],
        'opportunities'=>['organisation'=>'Organisation','deadline'=>'Deadline'],
        'events'=>['location'=>'Location','starts_at'=>'Starts'],
        'settings'=>[],default=>[],
    };
@endphp
<x-filter-bar
    :action="'/admin/'.$module.(($participantsOnly ?? false)?'?role=participant':'')"
    searchName="q"
    :search="$filters['q']"
    searchLabel="Search records"
    searchPlaceholder="Search"
    :statusOptions="$cmsStatusOptions"
    :status="$filters['status']"
    statusLabel="Status"
    :periodOptions="['all'=>'All time','week'=>'This week','month'=>'This month','year'=>'This year','custom'=>'Custom range']"
    :period="$filters['period']"
    periodLabel="Period"
    idPrefix="cms"
>
    <div class="field"><label for="cms-start">From</label><input id="cms-start" type="date" name="start_date" value="{{ $filters['start_date'] }}"></div>
    <div class="field"><label for="cms-end">To</label><input id="cms-end" type="date" name="end_date" value="{{ $filters['end_date'] }}"></div>
</x-filter-bar>
<div class="toolbar">
    @if(($participantsOnly ?? false)&&auth()->user()->manager())<a class="button secondary" href="/admin/enrollment"><i class="fas fa-user-graduate" aria-hidden="true"></i> Enrollment and cohorts</a>@endif
    @if($module==='lessons')<a class="button secondary" href="/admin/lessons/batch"><i class="fas fa-layer-group" aria-hidden="true"></i> Add multiple lessons</a>@endif
    @if($module!=='courses'||auth()->user()->manager())
    <button type="button" data-dialog-open="create-record"><i class="fas fa-plus" aria-hidden="true"></i> Add new</button>
    @endif
    <form id="cms-bulk-delete" class="page-bulk-actions" data-page-bulk-form method="post" action="/admin/{{ $module }}/bulk-delete">@csrf<button class="danger" type="submit" data-page-bulk-submit disabled>Delete selected (<span data-page-selected-count>0</span>)</button></form>
</div>
<div class="panel table-wrap cms-table-wrap"><table class="data-table cms-table">
    <caption class="sr-only">{{ $meta['title'] }} records</caption>
    <thead><tr><th scope="col" class="page-select-cell"><input type="checkbox" aria-label="Select all records on this page" data-page-select-all></th><th scope="col" class="column-primary">{{ $module==='users'?'Name':($module==='settings'?'Setting':'Title') }}</th>@foreach($tableColumns as $column=>$label)<th scope="col" class="{{ $column==='position'||$column==='pass_mark'?'column-number':'column-context' }}">{{ $label }}</th>@endforeach<th scope="col" class="column-status">Status</th><th scope="col" class="column-date">Updated</th><th scope="col" class="column-actions">Actions</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        @php $rowName = $row->title??$row->title_lg??$row->name??$row->key; @endphp
        <tr>
            <td class="page-select-cell"><input type="checkbox" name="record_ids[]" value="{{ $row->id }}" form="cms-bulk-delete" aria-label="Select {{ $rowName }}" data-page-row-select></td>
            <td class="column-primary"><strong>{{ $rowName }}</strong><small>Record #{{ $row->id }}</small></td>
            @foreach($tableColumns as $column=>$label)
                @php
                    $cell=match($column){
                        'instructors'=>$row->instructors->pluck('name')->implode(', '),
                        'content_format'=>ucfirst(\App\Services\LearningContent::format($row,$module)),
                        'program_id','course_id','mentor_id'=>$options[$column][$row->{$column}]??'—',
                        'starts_at','deadline'=>$row->{$column}?\Carbon\Carbon::parse($row->{$column})->format($column==='deadline'?'d M Y':'d M Y, H:i'):'—',
                        'content_language'=>($row->content_language??'en')==='lg'?'Luganda':'English', 'pass_mark'=>$row->pass_mark.'%', 'learner_count'=>$courseLearnerCounts[$row->id]??0, default=>$row->{$column}??'—',
                    };
                @endphp
                <td class="{{ in_array($column,['position','pass_mark','learner_count'],true)?'column-number':'column-context' }}">{{ $cell??'—' }}</td>
            @endforeach
            <td class="column-status">@if($row->status)<span class="status-badge" data-status="{{ $row->status }}">{{ ucfirst($row->status) }}</span>@else<span class="muted">—</span>@endif</td>
            <td class="column-date">{{ $row->updated_at?\Carbon\Carbon::parse($row->updated_at)->format('d M Y'):'—' }}</td>
            <td class="column-actions"><div class="actions">
                <x-record-view-trigger :dialogId="'view-record-'.$row->id" :name="$rowName ?? 'Record '.$row->id" />
                @if(($row->status??null)==='published'&&in_array($module,['pages','programs','courses','opportunities','events','posts']))<a class="secondary small icon-action" href="{{ $module==='pages'?'/pages/'.$row->slug:'/'.$module.'/'.$row->id }}" title="View published item" aria-label="View {{ $row->title??'published item' }}"><i class="fas fa-eye" aria-hidden="true"></i></a>@endif
                @if(($participantsOnly ?? false)&&auth()->user()->manager())<a class="secondary small" href="/admin/enrollment?status=all&amp;q={{ urlencode($row->name) }}" aria-label="Manage enrollment for {{ $row->name }}">Enrollment</a>@endif
                @if($module==='users'&&($row->requires_email_verification||$row->must_change_password))<form method="post" action="/admin/users/{{ $row->id }}/resend-setup">@csrf<button class="secondary small" type="submit">Resend setup email</button></form>@endif
                <button type="button" class="secondary small icon-action" data-dialog-open="edit-record-{{ $row->id }}" title="Edit record" aria-label="Edit record"><i class="fas fa-pen" aria-hidden="true"></i></button>
                <form method="post" action="/admin/{{ $module }}/{{ $row->id }}" data-confirm="Delete this record?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete record" aria-label="Delete record"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="{{ count($tableColumns)+5 }}" class="table-empty">Nothing here yet. Add your first record.</td></tr>
    @endforelse
    </tbody>
</table>@include('partials.pagination',['rows'=>$rows])</div>

@if($module!=='courses'||auth()->user()->manager())
<dialog class="form-dialog" id="create-record" aria-labelledby="create-record-title">
    <div class="dialog-heading"><h2 id="create-record-title">Create {{ \Illuminate\Support\Str::singular($meta['title']) }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    @php $record=null; @endphp
    <form class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}{{ ($participantsOnly ?? false)?'?role=participant':'' }}" data-upload-progress data-max-file-bytes="{{ \App\Services\UploadLimit::fileKilobytes()*1024 }}" data-max-request-bytes="{{ \App\Services\UploadLimit::requestBytes() }}">
        @csrf<input type="hidden" name="_modal_id" value="create-record">
        @include('admin.fields',['formId'=>'create-record','participantsOnly'=>$participantsOnly??false])
        <div class="upload-progress" data-upload-progress-status hidden><progress max="100" value="0"></progress><span aria-live="polite">Uploading…</span></div>
        <div class="dialog-actions"><button type="submit">Save record</button></div>
    </form>
</dialog>
@endif

@foreach($rows as $row)
    <dialog class="form-dialog" id="edit-record-{{ $row->id }}" aria-labelledby="edit-record-title-{{ $row->id }}">
        <div class="dialog-heading"><h2 id="edit-record-title-{{ $row->id }}">Edit record</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
        @php $record=$row; @endphp
        <form class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}/{{ $row->id }}{{ ($participantsOnly ?? false)?'?role=participant':'' }}" data-upload-progress data-max-file-bytes="{{ \App\Services\UploadLimit::fileKilobytes()*1024 }}" data-max-request-bytes="{{ \App\Services\UploadLimit::requestBytes() }}">
            @csrf @method('PUT')<input type="hidden" name="_modal_id" value="edit-record-{{ $row->id }}">
            @include('admin.fields',['formId'=>'edit-record-'.$row->id,'participantsOnly'=>$participantsOnly??false])
            <div class="upload-progress" data-upload-progress-status hidden><progress max="100" value="0"></progress><span aria-live="polite">Uploading…</span></div>
            <div class="dialog-actions"><button type="submit">Save changes</button></div>
        </form>
    </dialog>
@endforeach
@foreach($rows as $row)
    @php
        $rowName = $row->title??$row->title_lg??$row->name??$row->key;
        $attributes = method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
        $secretKeys = '/password|remember|token|secret|api_key|fcm/i';
        $isSecretSetting = $module==='settings' && preg_match('/api|secret|token|password|key/i', (string) $rowName);
        $viewFields = collect($attributes)
            ->reject(fn($value, $key) => preg_match($secretKeys, (string) $key) === 1 || ($isSecretSetting && $key === 'value'))
            ->map(fn($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value])
            ->values()->all();
    @endphp
    <x-record-view-dialog :id="'view-record-'.$row->id" :title="$rowName ?? 'Record '.$row->id" :fields="$viewFields" />
@endforeach
@if($errors->any() && old('_modal_id'))<div data-reopen-dialog="{{ old('_modal_id') }}" hidden></div>@endif
</div>
@endsection
