@extends('layout')
@section('content')
<span class="eyebrow"><i class="fas fa-folder-open" aria-hidden="true"></i> Content management</span>
<h1>{{ $meta['title'] }}</h1>
<div class="toolbar">
    <form class="filter-form" method="get">
        <div class="field search-field"><label for="cms-search">Search records</label><input id="cms-search" type="search" name="q" maxlength="255" placeholder="Search records" value="{{ $filters['q'] }}"></div>
        <div class="field"><label for="cms-period">Period</label><select id="cms-period" name="period">@foreach(['all'=>'All time','week'=>'This week','month'=>'This month','year'=>'This year','custom'=>'Custom range'] as $value=>$label)<option value="{{ $value }}" @selected($filters['period']===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="cms-start">From</label><input id="cms-start" type="date" name="start_date" value="{{ $filters['start_date'] }}"></div>
        <div class="field"><label for="cms-end">To</label><input id="cms-end" type="date" name="end_date" value="{{ $filters['end_date'] }}"></div>
        <div class="filter-actions"><button><i class="fas fa-search" aria-hidden="true"></i> Apply</button> <a href="/admin/{{ $module }}">Reset</a></div>
    </form>
    <button type="button" data-dialog-open="create-record"><i class="fas fa-plus" aria-hidden="true"></i> Add new</button>
</div>
<div class="panel table-wrap"><table>
    <thead><tr><th><i class="fas fa-hashtag" aria-hidden="true"></i> ID</th><th><i class="fas {{ $module==='users'?'fa-user':($module==='settings'?'fa-key':'fa-heading') }}" aria-hidden="true"></i> {{ $module==='users'?'Name':($module==='settings'?'Key':'Title') }}</th><th><i class="fas fa-info-circle" aria-hidden="true"></i> Status / role</th><th><i class="fas fa-sliders-h" aria-hidden="true"></i> Actions</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td>{{ $row->id }}</td>
            <td>{{ $row->title??$row->name??$row->key }}</td>
            <td>{{ $row->status??'' }} {{ $row->role??'' }}</td>
            <td><div class="actions">
                <button type="button" class="secondary small" data-dialog-open="edit-record-{{ $row->id }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>
                <form method="post" action="/admin/{{ $module }}/{{ $row->id }}" data-confirm="Delete this record?">@csrf @method('DELETE')<button class="danger small" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete</button></form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="4">No records yet.</td></tr>
    @endforelse
    </tbody>
</table>@include('partials.pagination',['rows'=>$rows])</div>

<dialog class="form-dialog" id="create-record" aria-labelledby="create-record-title">
    <div class="dialog-heading"><h2 id="create-record-title">Create {{ \Illuminate\Support\Str::singular($meta['title']) }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    @php $record=null; @endphp
    <form class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}">
        @csrf<input type="hidden" name="_modal_id" value="create-record">
        @include('admin.fields',['formId'=>'create-record'])
        <div class="dialog-actions"><button type="submit">Save record</button></div>
    </form>
</dialog>

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
@if($errors->any() && old('_modal_id'))<div data-reopen-dialog="{{ old('_modal_id') }}" hidden></div>@endif
@endsection
