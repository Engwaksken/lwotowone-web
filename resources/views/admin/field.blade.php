@php
    $optional=is_string($raw)&&str_starts_with($raw,'optional:');
    $type=$optional?substr($raw,9):$raw;
    $value=old($field,$record?->{$field});
@endphp
<div class="field" @if($module==='lessons'&&in_array($field,['title','title_lg','body_lg','content_body_lg'],true)) data-lesson-language-field="{{ in_array($field,['title_lg','body_lg','content_body_lg'],true)?'lg':'en' }}" @endif>
    <label for="{{ $formId }}-{{ $field }}">{{ $module==='lessons'&&$field==='title'?'Title (English)':\App\Services\RecordPresentation::label($field) }} {{ $optional?'(optional)':'' }}</label>
    @if(isset($options[$field]))
        <select id="{{ $formId }}-{{ $field }}" name="{{ $field }}" @required(!$optional) @isset($options['_course'][$field])data-course-filter @endisset>
            <option value="">Select</option>
            @foreach($options[$field] as $v=>$label)
                <option value="{{ $v }}" @selected((string)$value===(string)$v) @isset($options['_course'][$field][$v])data-course="{{ $options['_course'][$field][$v] }}"@endisset>{{ $label }}</option>
            @endforeach
        </select>
    @elseif($type==='textarea')
        <textarea id="{{ $formId }}-{{ $field }}" name="{{ $field }}" @required(!$optional)>{{ $value }}</textarea>
    @else
        @php
            $htmlType=match($type){'number','money'=>'number','datetime'=>'datetime-local','date'=>'date','email'=>'email','password'=>'password','url'=>'url','file'=>'file',default=>'text'};
            if($type==='datetime'&&$value)$value=\Carbon\Carbon::parse($value)->format('Y-m-d\TH:i');
            if($type==='date'&&$value)$value=\Carbon\Carbon::parse($value)->format('Y-m-d');
        @endphp
        <input id="{{ $formId }}-{{ $field }}" name="{{ $field }}" type="{{ $htmlType }}"
            @if($type==='file'&&$module==='resources')accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg"@endif
            @if(!in_array($type,['password','file']))value="{{ $value }}"@endif
            @if($type==='number')min="0"@endif
            @if($type==='money')min="0" step="0.01" inputmode="decimal" placeholder="Leave blank if free"@endif
            @if(!$optional && !($type==='file'&&$record))required @endif>
        @if($type==='file')<small>{{ $module==='resources'?'PDF, text, images, MP4, WebM or OGG video. Maximum 100 MB. Stored privately.':'PDF, text or image. Maximum 10 MB. Stored privately.' }}</small>@endif
    @endif
    @if($type==='money')<small>Optional. Leave blank for a free event. Learners are not charged during the free access period.</small>@endif
</div>
