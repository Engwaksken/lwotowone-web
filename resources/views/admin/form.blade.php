@extends('layout')
@section('content')
<div class="page-wrap">
    <a href="/admin/{{ $module }}">Back to {{ $meta['title'] }}</a>
    <h1 class="page-title">{{ $record?'Edit':'Create' }} record</h1>
    <form class="panel" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}{{ $record?'/'.$record->id:'' }}">@csrf @if($record)@method('PUT')@endif
        @include('admin.fields',['formId'=>'record'])
        @if(in_array($module,['lessons','assignments']))
            <div class="field"><label for="record-attachments">Additional files (up to 10)</label><input id="record-attachments" type="file" name="attachments[]" multiple accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg,.mp3,.wav,.m4a,.aac,.flac"><small>Each file can be up to 100 MB. Existing files remain attached when you edit.</small></div>
        @endif
        <button type="submit">Save record</button>
    </form>
</div>
@endsection
