@foreach($meta['fields'] as $field=>$raw)
    @continue($module==='courses' && $field==='instructor_id')
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
                @if($type==='file'&&$module==='resources')accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg"@endif
                @if(!in_array($type,['password','file']))value="{{ $value }}"@endif
                @if($type==='number')min="0"@endif
                @if(!$optional && !($type==='file'&&$record))required @endif>
            @if($type==='file')<small>{{ $module==='resources'?'PDF, text, images, MP4, WebM or OGG video. Maximum 100 MB. Stored privately.':'PDF, text or image. Maximum 10 MB. Stored privately.' }}</small>@endif
        @endif
    </div>
@endforeach
@if(in_array($module,['courses','programs']) && auth()->user()->role==='admin')
    @php
        $assigned=old('instructor_ids',$record?->assignedInstructorIds()??[]);
        $lead=old('lead_instructor_id',$record?->instructorLeads()->value('users.id'));
        $teachers=\App\Models\User::where('role','instructor')->where('status','active')->orderBy('name')->get(['id','name']);
    @endphp
    <input type="hidden" name="assignments_present" value="1">
    <div class="field">
        <label for="{{ $formId }}-instructors">Assigned instructors</label>
        <select id="{{ $formId }}-instructors" name="instructor_ids[]" multiple>
            @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(in_array($teacher->id,(array)$assigned))>{{ $teacher->name }}</option>@endforeach
        </select>
        <small>Select all instructors who should be assigned. Programme assignments do not grant course access.</small>
    </div>
    <div class="field">
        <label for="{{ $formId }}-lead">Lead instructor (optional)</label>
        <select id="{{ $formId }}-lead" name="lead_instructor_id">
            <option value="">No lead</option>
            @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((string)$lead===(string)$teacher->id)>{{ $teacher->name }}</option>@endforeach
        </select>
        <small>The lead must also be selected as an assigned instructor.</small>
    </div>
@endif
