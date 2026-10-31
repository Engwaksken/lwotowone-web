{{-- The learner-facing content of one lesson or resource. Expects $record and $module. --}}
@php
    $luganda = ($record->content_language ?? 'en') === 'lg';
    $pick = fn ($en, $lg) => $luganda ? ($record->{$lg} ?: $record->{$en}) : ($record->{$en} ?: $record->{$lg});
    $title = $pick('title', 'title_lg') ?: ($module === 'lessons' ? 'Lesson' : 'Resource');
    $source = \App\Services\LearningContent::source($record, $module);
    $text = match (true) {
        $module === 'lessons' => $luganda ? ($record->body_lg ?: $record->content_body_lg) : ($record->body ?: $record->content_body),
        $source === 'write' => $luganda ? $record->content_body_lg : $record->content_body,
        default => null,
    };
    $mediaUrl = match (true) {
        $source === 'upload' && (bool) $record->file_path => url('/learning/content/'.$module.'/'.$record->id),
        $source === 'url' => $record->content_url ?: ($module === 'lessons' ? $record->video_url : null),
        $module === 'lessons' && !empty($record->video_url) => $record->video_url,
        default => null,
    };
    $mediaType = !empty($record->video_url) ? 'video' : \App\Services\LearningContent::mediaType($record);
    $attachments = $module === 'lessons'
        ? \Illuminate\Support\Facades\DB::table('learning_attachments')->where('entity_type', 'lesson')->where('entity_id', $record->id)->get(['id', 'original_name'])
        : collect();
@endphp
<article class="panel content-preview-learner" @if($luganda) lang="lg" @else lang="en" @endif>
    @if($showTitle ?? true)<h3>{{ $title }}</h3>@endif
    @if($module === 'resources' && $pick('description', 'description_lg'))<p>{{ $pick('description', 'description_lg') }}</p>@endif
    @if($text)<div class="prose course-item-content">{{ $text }}</div>@endif
    @foreach($attachments as $attachment)<p><a href="{{ url('/files/attachments/'.$attachment->id) }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $attachment->original_name }}</a></p>@endforeach
    @if($mediaUrl)<x-learning-media :url="$mediaUrl" :type="$mediaType" :title="$title" :external="$source === 'url' || ($module === 'lessons' && $source !== 'upload' && !empty($record->video_url))" preload="none" />@endif
    @if(!$text && !$mediaUrl && $attachments->isEmpty())<p class="muted">No content has been added yet. Edit this {{ \Illuminate\Support\Str::singular($module) }} to add it.</p>@endif
</article>
