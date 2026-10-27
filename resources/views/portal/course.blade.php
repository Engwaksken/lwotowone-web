@extends('layout')
@section('title',$course->title.' | My learning')
@section('content')
<div class="page-wrap">
@php $luganda=auth()->user()->preferred_language==='lg'; $t=fn($en,$lg)=>$luganda?$lg:$en; @endphp
<a href="/portal/learn">{{ $t('Back to my learning','Ddayo ku bye nnyiga') }}</a>
<span class="eyebrow">{{ $t('Course workspace','Essomo lyange') }}</span>
<h1 class="page-title">{{ $course->title }}</h1>
<p class="course-summary">{{ $course->summary }}</p>
@php $courseAccess=collect($data['course_access'])->firstWhere('course_id',$course->id); @endphp
@if(!$courseAccess['allowed'])<div class="notice error">{{ $t('Lessons and resources are locked. Finish the prerequisite course if there is one, and confirm payment when your 12-hour trial ends.','Amasomo n’ebikozesebwa bigaddwa. Maliriza kkoosi esooka, bweba eriwo, era kakasa okusasula ng’ekiseera eky’okugezesa eky’essaawa 12 kiwedde.') }} <a href="/portal/payment">{{ $t('View payment instructions','Laba ebiragiro by’okusasula') }}</a>.</div>@elseif(\App\Services\FreeAccess::active())@include('partials.free-access-notice')@elseif(!$courseAccess['paid']&&$courseAccess['trial_expires_at'])<div class="notice">{{ $t('Trial access expires','Ekiseera eky’okugezesa kiggwaako') }} {{ $courseAccess['trial_expires_at'] }}. {{ $t('Lessons unlock in order as you finish each one.','Amasomo gasumululwa mu nsengeka ng’omaliriza buli limu.') }}</div>@endif
@include('portal.progress',['progressCourseId'=>$course->id])
@php
    $courseLessons=collect($data['lessons'])->where('course_id',$course->id)->sortBy('sort_order');
    $courseResources=collect($data['resources'])->where('course_id',$course->id);
    $courseAssignments=collect($data['assignments'])->where('course_id',$course->id);
@endphp
<div class="tabs course-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="{{ $t('Course content','Ebirimu essomo') }}">
        <button type="button" role="tab" id="tab-course-lessons" aria-controls="panel-course-lessons" aria-selected="true">{{ $t('Lessons','Amasomo') }} <span class="tab-count">{{ $courseLessons->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-resources" aria-controls="panel-course-resources" aria-selected="false" tabindex="-1">{{ $t('Resources','Ebikozesebwa') }} <span class="tab-count">{{ $courseResources->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-assignments" aria-controls="panel-course-assignments" aria-selected="false" tabindex="-1">{{ $t('Assignments','Emirimu') }} <span class="tab-count">{{ $courseAssignments->count() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-course-lessons" aria-labelledby="tab-course-lessons">
        @forelse($courseLessons as $lesson)
            @if($lesson->module_title&&($loop->first||$lesson->module_title!==($previousModule??null)))<h2>{{ $lesson->module_title }}</h2>@endif
            @php $previousModule=$lesson->module_title; @endphp
            @php $completed=collect($data['lesson_progress'])->contains('lesson_id',$lesson->id); @endphp
            <details class="course-item" id="lesson-{{ $lesson->id }}">
                <summary><span class="course-item-number">{{ str_pad($lesson->position,2,'0',STR_PAD_LEFT) }}</span><span>{{ $lesson->title??$lesson->title_lg??$t('Lesson','Essomo') }}</span><span class="tag">{{ $completed?$t('Completed','Kiwedde'):$t('Lesson','Essomo') }}</span></summary>
                @if($lesson->locked)<p><i class="fas fa-lock" aria-hidden="true"></i> {{ $t('Locked. Finish the earlier lessons and confirm payment if your trial has ended.','Kigaddwa. Maliriza amasomo agakulembera era kakasa okusasula singa ekiseera eky’okugezesa kiwedde.') }}</p>@elseif($lesson->language_unavailable)<p class="notice">{{ $t('This lesson is currently available in Luganda only. An English version has not been added yet.','Essomo lino kati liri mu Luganda yokka. Tewannateekebwawo nkyusa ya Lungereza.') }}</p>@else
                <div class="prose course-item-content">{{ $lesson->body }}</div>
                @foreach(($lesson->attachments??[]) as $attachment)<p><a href="{{ $attachment['url'] }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $attachment['name'] }}</a></p>@endforeach
                @php $mediaUrl=$lesson->viewer_url??$lesson->content_url??$lesson->video_url; @endphp
                @if($mediaUrl)<x-learning-media :url="$mediaUrl" :type="$lesson->video_url?'video':$lesson->media_type" :title="$lesson->title" :external="!$lesson->viewer_url" />@endif
                @if(!$completed)<form method="post" action="/actions/complete">@csrf<input type="hidden" name="lesson_id" value="{{ $lesson->id }}"><button type="submit">{{ $t('Mark complete','Teeka nti kiwedde') }}</button></form>@endif
                @endif
            </details>
        @empty<p class="muted">{{ $t('Lessons will appear here once they are published.','Amasomo ganaalabika wano nga gafulumiziddwa.') }}</p>
        @endforelse
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-resources" aria-labelledby="tab-course-resources" hidden>
        <div class="course-resource-list">
            @forelse($courseResources as $resource)
                <article class="panel course-resource"><div class="course-resource-heading"><div><h3>{{ $resource->title }}</h3><p>{{ $resource->description }}</p></div><span class="tag"><i class="fas {{ ($resource->media_type??'')==='video'?'fa-play-circle':(($resource->media_type??'')==='pdf'?'fa-file-pdf':'fa-book-open') }}" aria-hidden="true"></i> {{ ucfirst($resource->media_type??'resource') }}</span></div>
                    @if($resource->locked)<p class="muted">{{ $t('Locked. Finish earlier lessons or confirm payment.','Kigaddwa. Maliriza amasomo agakulembera oba kakasa okusasula.') }}</p>
                    @elseif($resource->content_source==='write')<div class="prose course-item-content">{{ $resource->content_body }}</div>
                    @elseif($resource->viewer_url||$resource->content_url)<x-learning-media :url="$resource->viewer_url??$resource->content_url" :type="$resource->media_type" :title="$resource->title" :external="!$resource->viewer_url" />
                    @else<p class="muted">{{ $t('No content attached yet.','Tewali birimu byateekeddwamu.') }}</p>@endif
                </article>
            @empty<p class="muted">{{ $t('No resources yet.','Tewali bikozesebwa.') }}</p>
            @endforelse
        </div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-assignments" aria-labelledby="tab-course-assignments" hidden>
        @forelse($courseAssignments as $assignment)
            @php $submission=collect($data['submissions'])->firstWhere('assignment_id',$assignment->id); @endphp
            <details class="course-item">
                <summary><span>{{ $assignment->title }}</span><span class="tag">{{ ucfirst($assignment->assessment_type??'assignment') }} · {{ $t('Pass mark','Obubonero obwetaagisa') }} {{ $assignment->pass_mark }}%</span></summary>
                @if($assignment->locked)<p>{{ $t('Locked until payment is confirmed.','Kigaddwa okutuusa ng’okusasula kukakasiddwa.') }}</p>@else
                <p class="prose course-item-content">{{ $assignment->instructions }}</p>
                @foreach(($assignment->attachments??[]) as $attachment)<p><a href="{{ $attachment['url'] }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $attachment['name'] }}</a></p>@endforeach
                @if($assignment->due_at)<p class="muted">{{ $t('Due','Lunaku olusembayo') }} {{ \Carbon\Carbon::parse($assignment->due_at)->format('d M Y, H:i') }}</p>@endif
                @if($submission)
                    <div class="submission-status"><strong>{{ $luganda?match($submission->status){'submitted'=>'Biweerezeddwa','passed'=>'Ayise','returned'=>'Aziziddwa','failed'=>'Agudde',default=>ucfirst($submission->status)}:ucfirst($submission->status) }}</strong><span>{{ $t('Score','Obubonero') }}: {{ $submission->score??$t('Awaiting review','Birindiridde okwekebejjebwa') }}</span></div>
                    @if($submission->feedback)<p>{{ $t('Feedback','Okuddamu') }}: {{ $submission->feedback }}</p>@endif
                @endif
                @if(!$submission||$submission->status==='returned')
                    <form class="assignment-form" method="post" enctype="multipart/form-data" action="/actions/submit">@csrf
                        <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                        <div class="field"><label for="assignment-body-{{ $assignment->id }}">{{ in_array($assignment->assessment_type??'assignment',['quiz','exam'])?'Your answers':'Your practical work' }}</label><textarea id="assignment-body-{{ $assignment->id }}" name="body" required></textarea></div>
                        <div class="field"><label for="assignment-file-{{ $assignment->id }}">{{ $t('Evidence files (optional, up to 10 files; 10 MB each)','Faayo eraga obukakafu (si kya buwaze)') }}</label><input id="assignment-file-{{ $assignment->id }}" type="file" name="files[]" accept=".pdf,.txt,.jpg,.jpeg,.png,.webp" multiple></div>
                        <button type="submit">{{ $t('Submit work','Weereza omulimu') }}</button>
                    </form>
                @endif
                @endif
            </details>
        @empty<p class="muted">{{ $t('No assignments yet.','Tewali mirimu gya kuweebwa.') }}</p>
        @endforelse
    </section>
</div>
</div>
@endsection
