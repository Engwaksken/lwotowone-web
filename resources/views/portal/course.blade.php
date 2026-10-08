@extends('layout')
@section('title',$course->title.' | My learning')
@section('content')
<a href="/portal/learn">Back to my learning</a>
<span class="eyebrow">Course workspace</span>
<h1>{{ $course->title }}</h1>
<p class="course-summary">{{ $course->summary }}</p>
@include('portal.progress',['progressCourseId'=>$course->id])
@php
    $courseLessons=collect($data['lessons'])->where('course_id',$course->id)->sortBy('position');
    $courseResources=collect($data['resources'])->where('course_id',$course->id);
    $courseAssignments=collect($data['assignments'])->where('course_id',$course->id);
@endphp
<div class="tabs course-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Course content">
        <button type="button" role="tab" id="tab-course-lessons" aria-controls="panel-course-lessons" aria-selected="true">Lessons <span class="tab-count">{{ $courseLessons->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-resources" aria-controls="panel-course-resources" aria-selected="false" tabindex="-1">Resources <span class="tab-count">{{ $courseResources->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-assignments" aria-controls="panel-course-assignments" aria-selected="false" tabindex="-1">Practical assignments <span class="tab-count">{{ $courseAssignments->count() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-course-lessons" aria-labelledby="tab-course-lessons">
        @forelse($courseLessons as $lesson)
            @php $completed=collect($data['lesson_progress'])->contains('lesson_id',$lesson->id); @endphp
            <details class="course-item" id="lesson-{{ $lesson->id }}">
                <summary><span class="course-item-number">{{ str_pad($lesson->position,2,'0',STR_PAD_LEFT) }}</span><span>{{ $lesson->title }}</span><span class="tag">{{ $completed?'Completed':'Lesson' }}</span></summary>
                <div class="prose course-item-content">{{ $lesson->body }}</div>
                @if($lesson->video_url)<p><a target="_blank" rel="noopener noreferrer" href="{{ $lesson->video_url }}">Watch lesson video (uses data)</a></p>@endif
                @if(!$completed)<form method="post" action="/actions/complete">@csrf<input type="hidden" name="lesson_id" value="{{ $lesson->id }}"><button type="submit">Mark lesson complete</button></form>@endif
            </details>
        @empty<p class="muted">Lessons will appear here when they are published.</p>
        @endforelse
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-resources" aria-labelledby="tab-course-resources" hidden>
        <div class="course-resource-list">
            @forelse($courseResources as $resource)
                <article class="panel course-resource"><div><h3>{{ $resource->title }}</h3><p>{{ $resource->description }}</p></div><a class="button secondary" href="/files/resources/{{ $resource->id }}">Download resource</a></article>
            @empty<p class="muted">There are no course resources yet.</p>
            @endforelse
        </div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-assignments" aria-labelledby="tab-course-assignments" hidden>
        @forelse($courseAssignments as $assignment)
            @php $submission=collect($data['submissions'])->firstWhere('assignment_id',$assignment->id); @endphp
            <details class="course-item">
                <summary><span>{{ $assignment->title }}</span><span class="tag">Pass mark {{ $assignment->pass_mark }}%</span></summary>
                <p class="prose course-item-content">{{ $assignment->instructions }}</p>
                @if($assignment->due_at)<p class="muted">Due {{ \Carbon\Carbon::parse($assignment->due_at)->format('d M Y, H:i') }}</p>@endif
                @if($submission)
                    <div class="submission-status"><strong>{{ ucfirst($submission->status) }}</strong><span>Score: {{ $submission->score??'Awaiting review' }}</span></div>
                    @if($submission->feedback)<p>Feedback: {{ $submission->feedback }}</p>@endif
                @endif
                @if(!$submission||$submission->status==='returned')
                    <form class="assignment-form" method="post" enctype="multipart/form-data" action="/actions/submit">@csrf
                        <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                        <div class="field"><label for="assignment-body-{{ $assignment->id }}">Your practical work</label><textarea id="assignment-body-{{ $assignment->id }}" name="body" required></textarea></div>
                        <div class="field"><label for="assignment-file-{{ $assignment->id }}">Evidence file (optional, up to 10 MB)</label><input id="assignment-file-{{ $assignment->id }}" type="file" name="file" accept=".pdf,.txt,.jpg,.jpeg,.png,.webp"></div>
                        <button type="submit">Submit practical work</button>
                    </form>
                @endif
            </details>
        @empty<p class="muted">There are no practical assignments for this course yet.</p>
        @endforelse
    </section>
</div>
@endsection
