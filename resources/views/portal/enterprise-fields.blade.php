@php $restore=old('form_key')===$formKey; @endphp
<input type="hidden" name="form_key" value="{{ $formKey }}">
@foreach(['title'=>'Enterprise name','sector'=>'Sector','idea'=>'Enterprise idea','business_plan'=>'Business plan (optional)'] as $field=>$label)
<div class="field">
    <label for="{{ $formKey }}-{{ $field }}">{{ $label }}</label>
    @if(in_array($field,['idea','business_plan']))
    <textarea id="{{ $formKey }}-{{ $field }}" name="{{ $field }}" maxlength="{{ $field==='idea'?20000:30000 }}" @required($field==='idea')>{{ $restore?old($field):($enterpriseRecord?->{$field}??'') }}</textarea>
    @else
    <input id="{{ $formKey }}-{{ $field }}" name="{{ $field }}" value="{{ $restore?old($field):($enterpriseRecord?->{$field}??'') }}" maxlength="{{ $field==='title'?255:100 }}" required>
    @endif
</div>
@endforeach
<div class="field"><label for="{{ $formKey }}-stage">Stage</label><select id="{{ $formKey }}-stage" name="stage" required>
    @foreach(['idea','planning','operating','growing'] as $stage)
    <option value="{{ $stage }}" @selected(($restore?old('stage'):($enterpriseRecord?->stage??'idea'))===$stage)>{{ ucfirst($stage) }}</option>
    @endforeach
</select></div>
