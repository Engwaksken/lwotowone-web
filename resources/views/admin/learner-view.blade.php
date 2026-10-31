@extends('layout')
@php
    $luganda = $language === 'lg';
    $languageName = $luganda ? 'Luganda' : 'English';
    $courseTitle = ($luganda ? ($course->title_lg ?: $course->title) : $course->title) ?: 'Course';
    $courseSummary = $luganda ? ($course->summary_lg ?: $course->summary) : $course->summary;
    $pick = fn ($row, $en, $lg) => $luganda ? ($row->{$lg} ?: $row->{$en}) : ($row->{$en} ?: $row->{$lg});
    $draftTotal = array_sum($drafts);
@endphp
@section('title','Learner view: '.$courseTitle)
@section('content')
<div class="page-wrap">
<span class="eyebrow">Learner view · {{ $languageName }}</span>
<h1 class="page-title">{{ $courseTitle }}</h1>
@if($courseSummary)<p class="page-intro" lang="{{ $language }}">{{ $courseSummary }}</p>@endif

<nav class="actions learner-view-languages" aria-label="Learner language">
    <span>View as:</span>
    @foreach(['en'=>'English learner','lg'=>'Luganda learner'] as $code=>$label)
        <a class="button small {{ $code===$language?'':'secondary' }}" href="/admin/courses/{{ $course->id }}/learner-view?language={{ $code }}" @if($code===$language) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a class="button small secondary" href="/admin/courses">Back to courses</a>
</nav>

<div class="notice" role="status">
    <strong>This is a preview.</strong> Every lesson is open here so you can check it. Learners unlock lessons one at a time, in this order, and only see published {{ $languageName }} content.
</div>
@if($course->status!=='published')<div class="notice error" role="status"><strong>This course is not published.</strong> Learners cannot see it yet.</div>@endif
@if($draftTotal)<p class="muted">Not shown because they are drafts: {{ collect($drafts)->filter()->map(fn($count,$type)=>$count.' '.\Illuminate\Support\Str::plural(\Illuminate\Support\Str::singular($type),$count))->implode(', ') }} in {{ $languageName }}.</p>@endif

<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Course content">
        <button type="button" role="tab" id="learner-view-tab-lessons" aria-controls="learner-view-panel-lessons" aria-selected="true">Lessons <span class="tab-count">{{ $lessons->count() }}</span></button>
        <button type="button" role="tab" id="learner-view-tab-resources" aria-controls="learner-view-panel-resources" aria-selected="false" tabindex="-1">Resources <span class="tab-count">{{ $resources->count() }}</span></button>
        <button type="button" role="tab" id="learner-view-tab-assignments" aria-controls="learner-view-panel-assignments" aria-selected="false" tabindex="-1">Assignments <span class="tab-count">{{ $assignments->count() }}</span></button>
    </div>

    <section class="tab-panel" role="tabpanel" id="learner-view-panel-lessons" aria-labelledby="learner-view-tab-lessons">
        @php $currentModule = false; @endphp
        @forelse($lessons as $index=>$lesson)
            @php $module = $luganda ? ($lesson->module_title_lg ?: $lesson->module_title) : $lesson->module_title; @endphp
            @if($module !== $currentModule)
                @if($module)<h2 class="learner-view-module">{{ $module }}</h2>@endif
                @php $currentModule = $module; @endphp
            @endif
            <details class="course-item" id="lesson-{{ $lesson->id }}" @if($index===0) open @endif>
                <summary><span class="course-item-number">{{ str_pad($index+1,2,'0',STR_PAD_LEFT) }}</span><span>{{ $pick($lesson,'title','title_lg') ?: 'Lesson' }}</span><span class="tag">{{ ucfirst(\App\Services\LearningContent::format($lesson,'lessons')) }}</span></summary>
                @include('admin.content-body',['record'=>$lesson,'module'=>'lessons','showTitle'=>false])
            </details>
        @empty
            <div class="empty">
                No published {{ $languageName }} lessons yet, so {{ $languageName }} learners see an empty course.
                @if($otherLanguage['lessons'])There {{ $otherLanguage['lessons']===1?'is':'are' }} {{ $otherLanguage['lessons'] }} published {{ \Illuminate\Support\Str::plural('lesson',$otherLanguage['lessons']) }} in {{ $luganda?'English':'Luganda' }}.@endif
            </div>
        @endforelse
    </section>

    <section class="tab-panel" role="tabpanel" id="learner-view-panel-resources" aria-labelledby="learner-view-tab-resources" hidden>
        @forelse($resources as $resource)
            @php $linked = $resource->lesson_id ? $lessons->firstWhere('id',$resource->lesson_id) : null; @endphp
            @if($linked)<p class="muted">Unlocks with lesson: {{ $pick($linked,'title','title_lg') }}</p>@endif
            @include('admin.content-body',['record'=>$resource,'module'=>'resources'])
        @empty
            <div class="empty">No published {{ $languageName }} resources.</div>
        @endforelse
    </section>

    <section class="tab-panel" role="tabpanel" id="learner-view-panel-assignments" aria-labelledby="learner-view-tab-assignments" hidden>
        @forelse($assignments as $assignment)
            <article class="panel content-preview-learner" lang="{{ $language }}">
                <h3>{{ $pick($assignment,'title','title_lg') ?: 'Assignment' }}</h3>
                <p class="content-preview-meta"><span class="tag">{{ ucfirst($assignment->assessment_type ?? 'assignment') }}</span><span class="tag">Pass mark {{ $assignment->pass_mark }}%</span>@if($assignment->due_at)<span class="tag">Due {{ \Illuminate\Support\Carbon::parse($assignment->due_at)->format('d M Y') }}</span>@endif</p>
                <div class="prose course-item-content">{{ $pick($assignment,'instructions','instructions_lg') }}</div>
                @foreach($attachments->get($assignment->id,[]) as $attachment)<p><a href="{{ url('/files/attachments/'.$attachment->id) }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $attachment->original_name }}</a></p>@endforeach
            </article>
        @empty
            <div class="empty">No published {{ $languageName }} assignments.@if(\App\Services\LearningAccess::awaitingAssessments((int)$course->id,$language)) Assignments exist in {{ $luganda?'English':'Luganda' }} only, so {{ $languageName }} learners cannot complete this course yet.@endif</div>
        @endforelse
    </section>
</div>
</div>
@endsection
