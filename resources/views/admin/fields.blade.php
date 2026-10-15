@php
    // Long forms are split into tabs so editors don't scroll through one tall column.
    $groups=['details'=>[],'content'=>[],'schedule'=>[],'payment'=>[]];$ordered=[];
    foreach($meta['fields'] as $field=>$raw){
        if($module==='courses'&&$field==='instructor_id')continue;
        if($module==='users'&&$field==='password')continue;
        if(in_array($module,['lessons','resources'])&&in_array($field,['body','video_url','file']))continue;
        $kind=is_string($raw)&&str_starts_with($raw,'optional:')?substr($raw,9):$raw;
        $group=match(true){$kind==='money'=>'payment',in_array($kind,['date','datetime'],true)=>'schedule',$kind==='textarea'=>'content',default=>'details'};
        $groups[$group][$field]=$raw;$ordered[$field]=$raw;
    }
    $hasContentFormats=in_array($module,['lessons','resources']);
    $hasTeam=in_array($module,['courses','programs'])&&auth()->user()->role==='admin';
    $labels=['details'=>'Details','content'=>$module==='events'?'Description':'Content','schedule'=>'Schedule','payment'=>'Payment','team'=>'Instructors'];
    $tabs=array_keys(array_filter($groups+['team'=>$hasTeam?[1]:[]]));
    if($hasContentFormats&&!in_array('content',$tabs,true))array_splice($tabs,1,0,['content']);
    $fieldCount=array_sum(array_map('count',$groups))+($hasContentFormats?3:0)+($hasTeam?2:0);
    $tabbed=count($tabs)>1&&$fieldCount>=6;
@endphp
@if($tabbed)
<div class="tabs form-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Form sections">
        @foreach($tabs as $i=>$tab)<button type="button" role="tab" id="{{ $formId }}-tab-{{ $tab }}" aria-controls="{{ $formId }}-panel-{{ $tab }}" aria-selected="{{ $i===0?'true':'false' }}" @if($i)tabindex="-1"@endif>{{ $labels[$tab] }}</button>@endforeach
    </div>
@endif
@foreach($tabs?:['details'] as $i=>$tab)
    @if($tabbed)<section class="tab-panel" role="tabpanel" id="{{ $formId }}-panel-{{ $tab }}" aria-labelledby="{{ $formId }}-tab-{{ $tab }}" @if($i)hidden @endif>@endif
    @foreach($tabbed?($groups[$tab]??[]):$ordered as $field=>$raw)
        @include('admin.field')
    @endforeach
    @if($hasContentFormats&&(!$tabbed||$tab==='content'))
        @include('admin.content-fields')
    @endif
    @if($hasTeam&&(!$tabbed||$tab==='team'))
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
    @if($tabbed)</section>@endif
@endforeach
@if($tabbed)</div>@endif
