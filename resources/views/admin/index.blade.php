@extends('layout')
@section('content')
<div class="page-wrap">
<span class="eyebrow"><i class="fas fa-folder-open" aria-hidden="true"></i> Content management</span>
<h1 class="page-title">{{ $meta['title'] }}</h1>
@include('partials.platform-stats')
@php
    $cmsStatusOptions = $statusOptions
        ? collect($statusOptions)->mapWithKeys(fn($status)=>[$status=>ucfirst($status)])->prepend('All statuses','all')->all()
        : [];
@endphp
<x-filter-bar
    :action="'/admin/'.$module"
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
    @if($module!=='courses'||auth()->user()->manager())
    <button type="button" data-dialog-open="create-record"><i class="fas fa-plus" aria-hidden="true"></i> Add new</button>
    @endif
</div>
<div class="panel table-wrap"><table>
    <thead><tr><th><i class="fas fa-hashtag" aria-hidden="true"></i> ID</th><th><i class="fas {{ $module==='users'?'fa-user':($module==='settings'?'fa-key':'fa-heading') }}" aria-hidden="true"></i> {{ $module==='users'?'Name':($module==='settings'?'Key':'Title') }}</th><th><i class="fas fa-info-circle" aria-hidden="true"></i> Status / role</th><th><i class="fas fa-sliders-h" aria-hidden="true"></i> Actions</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        @php $rowName = $row->title??$row->name??$row->key; @endphp
        <tr>
            <td>{{ $row->id }}</td>
            <td>{{ $rowName }}</td>
            <td>{{ $row->status??'' }} {{ $row->role??'' }}</td>
            <td><div class="actions">
                <x-record-view-trigger :dialogId="'view-record-'.$row->id" :name="$rowName ?? 'Record '.$row->id" />
                @if(($row->status??null)==='published'&&in_array($module,['pages','programs','courses','opportunities','events','posts']))<a class="secondary small icon-action" href="{{ $module==='pages'?'/pages/'.$row->slug:'/explore/'.$module.'/'.$row->id }}" title="View published item" aria-label="View {{ $row->title??'published item' }}"><i class="fas fa-eye" aria-hidden="true"></i></a>@endif
                <button type="button" class="secondary small icon-action" data-dialog-open="edit-record-{{ $row->id }}" title="Edit record" aria-label="Edit record"><i class="fas fa-pen" aria-hidden="true"></i></button>
                <form method="post" action="/admin/{{ $module }}/{{ $row->id }}" data-confirm="Delete this record?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete record" aria-label="Delete record"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="4">Nothing here yet. Add your first record.</td></tr>
    @endforelse
    </tbody>
</table>@include('partials.pagination',['rows'=>$rows])</div>

@if($module!=='courses'||auth()->user()->manager())
<dialog class="form-dialog" id="create-record" aria-labelledby="create-record-title">
    <div class="dialog-heading"><h2 id="create-record-title">Create {{ \Illuminate\Support\Str::singular($meta['title']) }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    @php $record=null; @endphp
    <form class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}">
        @csrf<input type="hidden" name="_modal_id" value="create-record">
        @include('admin.fields',['formId'=>'create-record'])
        <div class="dialog-actions"><button type="submit">Save record</button></div>
    </form>
</dialog>
@endif

@foreach($rows as $row)
    <dialog class="form-dialog" id="edit-record-{{ $row->id }}" aria-labelledby="edit-record-title-{{ $row->id }}">
        <div class="dialog-heading"><h2 id="edit-record-title-{{ $row->id }}">Edit record</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
        @php $record=$row; @endphp
        <form class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}/{{ $row->id }}">
            @csrf @method('PUT')<input type="hidden" name="_modal_id" value="edit-record-{{ $row->id }}">
            @include('admin.fields',['formId'=>'edit-record-'.$row->id])
            <div class="dialog-actions"><button type="submit">Save changes</button></div>
        </form>
    </dialog>
@endforeach
@foreach($rows as $row)
    @php
        $rowName = $row->title??$row->name??$row->key;
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
