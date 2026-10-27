@extends('layout')
@section('content')
<div class="page-wrap">
    <a href="/admin/lessons">Back to lessons</a>
    <h1 class="page-title">Add multiple lessons</h1>
    <p class="page-intro">Create several lessons for one course in a single save. Optional files can be attached to each lesson.</p>
    <form class="panel" method="post" action="/admin/lessons/batch" enctype="multipart/form-data">@csrf
        <div class="field"><label for="batch-course">Course</label><select id="batch-course" name="course_id" required><option value="">Choose a course</option>@foreach($courses as $id=>$title)<option value="{{ $id }}" @selected(old('course_id')==$id)>{{ $title }}</option>@endforeach</select></div>
        <div class="field"><label for="batch-status">Status for all lessons</label><select id="batch-status" name="status"><option value="draft">Draft</option><option value="published">Published</option></select></div>
        @for($i=0;$i<5;$i++)
            <fieldset class="panel"><legend>Lesson {{ $i+1 }}</legend>
                <div class="field"><label for="lesson-title-{{ $i }}">Title</label><input id="lesson-title-{{ $i }}" name="lessons[{{ $i }}][title]" value="{{ old("lessons.$i.title") }}" maxlength="255" @required($i===0)></div>
                <div class="field"><label for="lesson-body-{{ $i }}">Lesson content</label><textarea id="lesson-body-{{ $i }}" name="lessons[{{ $i }}][body]" maxlength="50000" @required($i===0)>{{ old("lessons.$i.body") }}</textarea></div>
                <div class="field"><label for="lesson-file-{{ $i }}">Attachment (optional)</label><input id="lesson-file-{{ $i }}" type="file" name="lessons[{{ $i }}][file]" accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg,.mp3,.wav,.m4a,.aac,.flac"></div>
            </fieldset>
        @endfor
        <button type="submit">Save lessons</button>
    </form>
</div>
@endsection
