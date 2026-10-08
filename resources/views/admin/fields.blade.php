@foreach($meta['fields'] as $field=>$raw)
    @php
        $optional=is_string($raw)&&str_starts_with($raw,'optional:');
        $type=$optional?substr($raw,9):$raw;
        $value=old($field,$record?->{$field});
    @endphp
    <div class="field">
        <label for="{{ $formId }}-{{ $field }}">{{ ucwords(str_replace('_',' ',$field)) }} {{ $optional?'(optional)':'' }}</label>
        @if(isset($options[$field]))
            <select id="{{ $formId }}-{{ $field }}" name="{{ $field }}" @required(!$optional)>
                <option value="">Select</option>
                @foreach($options[$field] as $v=>$label)
                    <option value="{{ $v }}" @selected((string)$value===(string)$v)>{{ $label }}</option>
                @endforeach
            </select>
        @elseif($type==='textarea')
            <textarea id="{{ $formId }}-{{ $field }}" name="{{ $field }}" @required(!$optional)>{{ $value }}</textarea>
        @else
            @php
                $htmlType=match($type){'number'=>'number','datetime'=>'datetime-local','date'=>'date','email'=>'email','password'=>'password','url'=>'url','file'=>'file',default=>'text'};
                if($type==='datetime'&&$value)$value=\Carbon\Carbon::parse($value)->format('Y-m-d\TH:i');
                if($type==='date'&&$value)$value=\Carbon\Carbon::parse($value)->format('Y-m-d');
            @endphp
            <input id="{{ $formId }}-{{ $field }}" name="{{ $field }}" type="{{ $htmlType }}"
                @if(!in_array($type,['password','file']))value="{{ $value }}"@endif
                @if($type==='number')min="0"@endif
                @if(!$optional && !($type==='file'&&$record))required @endif>
            @if($type==='file')<small>PDF, text or image. Maximum 10 MB. Stored privately.</small>@endif
        @endif
    </div>
@endforeach
