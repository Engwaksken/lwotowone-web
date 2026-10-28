@extends('layout')
@section('content')
<div class="page-wrap">
    <a href="/admin/{{ $module }}">Back to {{ $meta['title'] }}</a>
    <h1 class="page-title">{{ $record?'Edit':'Create' }} record</h1>
    @if($module==='pages'&&$record?->slug==='home')<p class="page-intro">This is the website home page. The title is the main headline. In the body, the first paragraph appears under the headline and the remaining paragraphs fill “How it works here” (leave a blank line between paragraphs). Keep the slug as <strong>home</strong>; programmes, courses, events and stories are added automatically.</p>@endif
    <form class="panel" method="post" enctype="multipart/form-data" action="/admin/{{ $module }}{{ $record?'/'.$record->id:'' }}" data-upload-progress data-max-file-bytes="{{ \App\Services\UploadLimit::fileKilobytes()*1024 }}" data-max-request-bytes="{{ \App\Services\UploadLimit::requestBytes() }}">@csrf @if($record)@method('PUT')@endif
        @include('admin.fields',['formId'=>'record'])
        @if(in_array($module,['lessons','assignments']))
            <div class="field"><label for="record-attachments">Additional files (up to 10)</label><input id="record-attachments" type="file" name="attachments[]" multiple accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg,.ogv,.mov,.m4v,.mp3,.wav,.m4a,.aac,.flac"><small>Each file can be up to {{ \App\Services\UploadLimit::fileMegabytes() }} MB. Existing files remain attached when you edit.</small></div>
        @endif
        <div class="upload-progress" data-upload-progress-status hidden><progress max="100" value="0"></progress><span aria-live="polite">Uploading…</span></div>
        <button type="submit">Save record</button>
    </form>
</div>
@endsection
