{{--
    Shows a lesson or resource the way a learner in its language sees it.
    Media sits in a closed dialog and uses preload="none" / lazy iframes, so nothing loads until the dialog is opened and played.
--}}
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
<dialog class="form-dialog content-preview-dialog" id="preview-record-{{ $record->id }}" aria-labelledby="preview-record-title-{{ $record->id }}">
    <div class="dialog-heading"><h2 id="preview-record-title-{{ $record->id }}">Preview: {{ $title }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    <p class="content-preview-meta">
        <span class="tag">{{ $luganda ? 'Luganda' : 'English' }}</span>
        <span class="tag">{{ ucfirst($mediaUrl ? $mediaType : ($text ? 'text' : 'no content')) }}</span>
        @if(($record->status ?? null) !== 'published')<span class="tag">Draft: learners cannot see this yet</span>@endif
    </p>
    <p class="muted">Shown as {{ $luganda ? 'Luganda' : 'English' }} learners see it.</p>
    <article class="panel content-preview-learner" @if($luganda) lang="lg" @else lang="en" @endif>
        <h3>{{ $title }}</h3>
        @if($module === 'resources' && $pick('description', 'description_lg'))<p>{{ $pick('description', 'description_lg') }}</p>@endif
        @if($text)<div class="prose course-item-content">{{ $text }}</div>@endif
        @foreach($attachments as $attachment)<p><a href="{{ url('/files/attachments/'.$attachment->id) }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $attachment->original_name }}</a></p>@endforeach
        @if($mediaUrl)<x-learning-media :url="$mediaUrl" :type="$mediaType" :title="$title" :external="$source === 'url' || ($module === 'lessons' && $source !== 'upload' && !empty($record->video_url))" preload="none" />@endif
        @if(!$text && !$mediaUrl && $attachments->isEmpty())<p class="muted">No content has been added yet. Edit this {{ \Illuminate\Support\Str::singular($module) }} to add it.</p>@endif
    </article>
    <div class="dialog-actions"><button type="button" class="secondary" data-dialog-close data-dialog-open="edit-record-{{ $record->id }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit this {{ \Illuminate\Support\Str::singular($module) }}</button></div>
</dialog>
