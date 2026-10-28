@php
    $format=old('content_format',\App\Services\LearningContent::format($record,$module));
    $source=old('content_source',\App\Services\LearningContent::source($record,$module));
    $hasFile=(bool)$record?->file_path;
    // Staff can watch what is saved now, through the same protected route learners use.
    $savedSource=$record?\App\Services\LearningContent::source($record,$module):null;
    $previewUrl=match(true){
        $record&&$savedSource==='upload'&&$hasFile=>url('/learning/content/'.$module.'/'.$record->id),
        $record&&$savedSource==='url'=>$record->content_url??($module==='lessons'?$record->video_url:null),
        default=>null,
    };
    $previewType=$previewUrl?(!empty($record->video_url)?'video':\App\Services\LearningContent::mediaType($record)):null;
@endphp
<fieldset class="learning-content-fields" data-content-fields data-has-file="{{ $hasFile?'1':'0' }}" data-original-format="{{ \App\Services\LearningContent::format($record,$module) }}" data-max-mb="{{ \App\Services\UploadLimit::fileMegabytes() }}">
    <legend>Content format and source</legend>
    @if($previewUrl)
        <div class="content-preview"><strong>Saved {{ in_array($previewType,['video','audio','image'],true)?$previewType:'content' }}</strong>
            <x-learning-media :url="$previewUrl" :type="$previewType" :title="$record->title??'Learning content'" :external="$savedSource==='url'" />
        </div>
    @endif
    <div class="content-choice-grid">
        <div class="field"><label for="{{ $formId }}-format">Format</label><select id="{{ $formId }}-format" name="content_format" required data-content-format>
            @foreach(\App\Services\LearningContent::FORMATS as $key=>$label)<option value="{{ $key }}" @selected($format===$key)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="field"><label for="{{ $formId }}-source">Content source</label><select id="{{ $formId }}-source" name="content_source" required data-content-source>
            <option value="write" @selected($source==='write')>Write text</option><option value="upload" @selected($source==='upload')>Upload a file</option><option value="url" @selected($source==='url')>Use a URL</option>
        </select></div>
    </div>
    <div class="field" data-content-panel="write" @if($module==='lessons')data-lesson-language-field="en"@endif><label for="{{ $formId }}-content-body">{{ $module==='lessons'?'Text content (English)':'Text content' }}</label><textarea id="{{ $formId }}-content-body" name="content_body" rows="7">{{ old('content_body',$record?->content_body??($module==='lessons'?$record?->body:'')) }}</textarea></div>
    <div class="field" data-content-panel="upload"><label for="{{ $formId }}-content-file">Upload {{ strtolower(\App\Services\LearningContent::FORMATS[$format]??'content') }}</label><input id="{{ $formId }}-content-file" name="file" type="file"><small data-content-file-help>Maximum {{ \App\Services\UploadLimit::fileMegabytes() }} MB. @if($hasFile)A file is already attached. Leave this empty to keep it, or upload a replacement.@endif</small><div class="content-preview" data-content-file-preview hidden></div></div>
    <div class="field" data-content-panel="url"><label for="{{ $formId }}-content-url">Content URL</label><input id="{{ $formId }}-content-url" type="url" name="content_url" maxlength="1000" value="{{ old('content_url',$record?->content_url??($module==='lessons'?$record?->video_url:'')) }}" placeholder="https://"><small>Use a public HTTPS link to your document, audio or video. YouTube and Vimeo videos are supported.</small></div>
</fieldset>
