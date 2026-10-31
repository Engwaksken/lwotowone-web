{{--
    Shows a lesson or resource the way a learner in its language sees it.
    Media sits in a closed dialog and uses preload="none" / lazy iframes, so nothing loads until the dialog is opened and played.
--}}
@php
    $luganda = ($record->content_language ?? 'en') === 'lg';
    $title = ($luganda ? ($record->title_lg ?: $record->title) : ($record->title ?: $record->title_lg)) ?: ($module === 'lessons' ? 'Lesson' : 'Resource');
    $source = \App\Services\LearningContent::source($record, $module);
    $hasMedia = ($source === 'upload' && (bool) $record->file_path) || ($source === 'url' && ($record->content_url || ($module === 'lessons' && $record->video_url))) || ($module === 'lessons' && !empty($record->video_url));
    $hasText = $module === 'lessons' ? (bool) ($luganda ? ($record->body_lg ?: $record->content_body_lg) : ($record->body ?: $record->content_body)) : ($source === 'write' && ($luganda ? $record->content_body_lg : $record->content_body));
    $mediaType = !empty($record->video_url) ? 'video' : \App\Services\LearningContent::mediaType($record);
@endphp
<dialog class="form-dialog content-preview-dialog" id="preview-record-{{ $record->id }}" aria-labelledby="preview-record-title-{{ $record->id }}">
    <div class="dialog-heading"><h2 id="preview-record-title-{{ $record->id }}">Preview: {{ $title }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    <p class="content-preview-meta">
        <span class="tag">{{ $luganda ? 'Luganda' : 'English' }}</span>
        <span class="tag">{{ ucfirst($hasMedia ? $mediaType : ($hasText ? 'text' : 'no content')) }}</span>
        @if(($record->status ?? null) !== 'published')<span class="tag">Draft: learners cannot see this yet</span>@endif
    </p>
    <p class="muted">Shown as {{ $luganda ? 'Luganda' : 'English' }} learners see it.</p>
    @include('admin.content-body',['record'=>$record,'module'=>$module])
    <div class="dialog-actions"><button type="button" class="secondary" data-dialog-close data-dialog-open="edit-record-{{ $record->id }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit this {{ \Illuminate\Support\Str::singular($module) }}</button></div>
</dialog>
