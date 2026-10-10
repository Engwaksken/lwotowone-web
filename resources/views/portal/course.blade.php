@extends('layout')
@section('title',$course->title.' | My learning')
@section('content')
<div class="page-wrap">
<a href="/portal/learn">Back to my learning</a>
<span class="eyebrow">Course workspace</span>
<h1 class="page-title">{{ $course->title }}</h1>
<p class="course-summary">{{ $course->summary }}</p>
@php $courseAccess=collect($data['course_access'])->firstWhere('course_id',$course->id); @endphp
@if(!$courseAccess['allowed'])<div class="notice error">Lessons and resources are locked. Finish the prerequisite course if there is one, and confirm payment when your 12-hour trial ends. <a href="/portal/payment">View payment instructions</a>.</div>@elseif(!$courseAccess['paid']&&$courseAccess['trial_expires_at'])<div class="notice">Trial access expires {{ $courseAccess['trial_expires_at'] }}. Lessons unlock in order as you finish each one.</div>@endif
@include('portal.progress',['progressCourseId'=>$course->id])
@php
    $courseLessons=collect($data['lessons'])->where('course_id',$course->id)->sortBy('sort_order');
    $courseResources=collect($data['resources'])->where('course_id',$course->id);
    $courseAssignments=collect($data['assignments'])->where('course_id',$course->id);
@endphp
<div class="tabs course-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Course content">
        <button type="button" role="tab" id="tab-course-lessons" aria-controls="panel-course-lessons" aria-selected="true">Lessons <span class="tab-count">{{ $courseLessons->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-resources" aria-controls="panel-course-resources" aria-selected="false" tabindex="-1">Resources <span class="tab-count">{{ $courseResources->count() }}</span></button>
        <button type="button" role="tab" id="tab-course-assignments" aria-controls="panel-course-assignments" aria-selected="false" tabindex="-1">Assignments <span class="tab-count">{{ $courseAssignments->count() }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-course-lessons" aria-labelledby="tab-course-lessons">
        @forelse($courseLessons as $lesson)
            @if($lesson->module_title&&($loop->first||$lesson->module_title!==($previousModule??null)))<h2>{{ $lesson->module_title }}</h2>@endif
            @php $previousModule=$lesson->module_title; @endphp
            @php $completed=collect($data['lesson_progress'])->contains('lesson_id',$lesson->id); @endphp
            <details class="course-item" id="lesson-{{ $lesson->id }}">
                <summary><span class="course-item-number">{{ str_pad($lesson->position,2,'0',STR_PAD_LEFT) }}</span><span>{{ $lesson->title }}</span><span class="tag">{{ $completed?'Completed':'Lesson' }}</span></summary>
                @if($lesson->locked)<p><i class="fas fa-lock" aria-hidden="true"></i> Locked. Finish the earlier lessons and confirm payment if your trial has ended.</p>@else
                <div class="prose course-item-content">{{ $lesson->body }}</div>
                @if($lesson->video_url)
                    @php
                        $videoHost=strtolower((string)parse_url($lesson->video_url,PHP_URL_HOST));
                        $videoPath=(string)parse_url($lesson->video_url,PHP_URL_PATH);
                        $videoId=null;$embedUrl=null;
                        if(str_contains($videoHost,'youtu.be'))$videoId=trim($videoPath,'/');
                        elseif(str_contains($videoHost,'youtube.com')){parse_str((string)parse_url($lesson->video_url,PHP_URL_QUERY),$videoQuery);$videoId=$videoQuery['v']??(preg_match('~/(?:embed|shorts)/([A-Za-z0-9_-]{6,})~',$videoPath,$videoMatch)?$videoMatch[1]:null);}
                        if($videoId&&preg_match('/^[A-Za-z0-9_-]{6,20}$/',$videoId))$embedUrl='https://www.youtube-nocookie.com/embed/'.$videoId.'?rel=0&modestbranding=1';
                        elseif(str_contains($videoHost,'vimeo.com')&&preg_match('~/(?:video/)?([0-9]{5,})~',$videoPath,$videoMatch))$embedUrl='https://player.vimeo.com/video/'.$videoMatch[1];
                        $directVideo=preg_match('/\.(mp4|webm|ogg)(?:$|[?#])/i',$lesson->video_url)===1;
                    @endphp
                    @if($embedUrl)<div class="lesson-video"><iframe src="{{ $embedUrl }}" title="{{ $lesson->title }} video" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                    @elseif($directVideo)<div class="lesson-video"><video controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline preload="metadata"><source src="{{ $lesson->video_url }}">Your browser cannot play this video.</video></div>
                    @else<p class="muted">This video can't play here. Contact your instructor.</p>@endif
                @endif
                @if(!$completed)<form method="post" action="/actions/complete">@csrf<input type="hidden" name="lesson_id" value="{{ $lesson->id }}"><button type="submit">Mark complete</button></form>@endif
                @endif
            </details>
        @empty<p class="muted">Lessons will appear here once they are published.</p>
        @endforelse
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-resources" aria-labelledby="tab-course-resources" hidden>
        <div class="course-resource-list">
            @forelse($courseResources as $resource)
                <article class="panel course-resource"><div class="course-resource-heading"><div><h3>{{ $resource->title }}</h3><p>{{ $resource->description }}</p></div><span class="tag"><i class="fas {{ ($resource->media_type??'')==='video'?'fa-play-circle':(($resource->media_type??'')==='pdf'?'fa-file-pdf':'fa-book-open') }}" aria-hidden="true"></i> {{ ucfirst($resource->media_type??'resource') }}</span></div>
                    @if(!empty($resource->viewer_url))
                        @if($resource->media_type==='video')<video class="resource-video" controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline preload="metadata"><source src="{{ $resource->viewer_url }}">Your browser cannot play this video.</video>
                        @elseif($resource->media_type==='pdf')<div class="resource-document-viewer"><iframe src="{{ $resource->viewer_url }}#toolbar=0&navpanes=0" title="{{ $resource->title }} document" loading="lazy"></iframe></div>
                        @elseif($resource->media_type==='image')<img class="resource-image-viewer" src="{{ $resource->viewer_url }}" alt="{{ $resource->title }}">
                        @elseif($resource->media_type==='text')<div class="resource-document-viewer"><iframe src="{{ $resource->viewer_url }}" title="{{ $resource->title }} text" loading="lazy"></iframe></div>@endif
                    @else<p class="muted">{{ $resource->locked?'Locked. Finish earlier lessons or confirm payment.':'No file attached yet.' }}</p>@endif
                </article>
            @empty<p class="muted">No resources yet.</p>
            @endforelse
        </div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-course-assignments" aria-labelledby="tab-course-assignments" hidden>
        @forelse($courseAssignments as $assignment)
            @php $submission=collect($data['submissions'])->firstWhere('assignment_id',$assignment->id); @endphp
            <details class="course-item">
                <summary><span>{{ $assignment->title }}</span><span class="tag">Pass mark {{ $assignment->pass_mark }}%</span></summary>
                @if($assignment->locked)<p>Locked until payment is confirmed.</p>@else
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
                        <button type="submit">Submit work</button>
                    </form>
                @endif
                @endif
            </details>
        @empty<p class="muted">No assignments yet.</p>
        @endforelse
    </section>
</div>
</div>
@endsection
