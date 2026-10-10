@extends('layout')
@section('title',$survey->title.' | Survey')
@section('inline-form-feedback','1')
@section('content')
<div class="page-wrap public-form-page"><span class="eyebrow">{{ $survey->anonymous?'Anonymous survey':'Survey' }}</span><h1 class="page-title">{{ $survey->title }}</h1>
<form class="public-response-form" method="post" action="/surveys/{{ $survey->token }}">@csrf<input type="hidden" name="submission_token" value="{{ old('submission_token',(string)\Illuminate\Support\Str::uuid()) }}">
@if($survey->description)<section class="panel survey-form-intro"><p class="prose">{{ $survey->description }}</p></section>@endif
<x-form-feedback :showSuccess="session('survey_received')===$survey->token" />
@if(session('survey_received')===$survey->token)
@elseif(!$open)<div class="notice">This survey is not currently accepting responses.</div>
@else
@if($survey->anonymous)<div class="notice">This survey is anonymous. Your account, name and email will not be attached to your response. Please do not include identifying details in your answers.</div>@else<section class="panel"><h2>Your details</h2><div class="field"><label for="respondent-name">Full name</label><input id="respondent-name" name="respondent_name" maxlength="255" value="{{ old('respondent_name',auth()->user()?->name) }}" required></div><div class="field"><label for="respondent-email">Email</label><input id="respondent-email" type="email" name="respondent_email" maxlength="255" value="{{ old('respondent_email',auth()->user()?->email) }}" required></div></section>@endif
@foreach($questions as $question)
<section class="panel survey-answer"><label class="survey-answer-label" @if($question['type']!=='multiple_choice')for="answer-{{ $question['id'] }}"@endif>{{ $loop->iteration }}. {{ $question['label'] }} @if($question['required'])<span aria-label="required">*</span>@endif</label>@if($question['help'])<p class="muted">{{ $question['help'] }}</p>@endif
@if($question['type']==='long_text')<textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" maxlength="10000" rows="5" @required($question['required'])>{{ old('answers.'.$question['id']) }}</textarea>
@elseif(in_array($question['type'],['single_choice','rating']))<select id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" @required($question['required'])><option value="">Select an answer</option>@foreach($question['type']==='rating'?range(1,5):$question['options'] as $option)<option value="{{ $option }}" @selected((string)old('answers.'.$question['id'])===(string)$option)>{{ $option }}</option>@endforeach</select>@if($question['type']==='rating')<small>1 = lowest · 5 = highest</small>@endif
@elseif($question['type']==='multiple_choice')<fieldset class="survey-choice-list" @if($question['required']) data-required-choice @endif><legend class="sr-only">{{ $question['label'] }}{{ $question['required']?' (choose at least one)':'' }}</legend>@foreach($question['options'] as $option)<label><input type="checkbox" name="answers[{{ $question['id'] }}][]" value="{{ $option }}" @checked(in_array($option,(array)old('answers.'.$question['id'],[])))> {{ $option }}</label>@endforeach</fieldset>
@else<input id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" type="{{ $question['type']==='short_text'?'text':$question['type'] }}" value="{{ old('answers.'.$question['id']) }}" @if($question['type']==='short_text')maxlength="500"@elseif($question['type']==='number')step="any" min="-999999999" max="999999999"@endif @required($question['required'])>@endif
@error('answers.'.$question['id'])<p class="field-error">{{ $message }}</p>@enderror</section>
@endforeach<button type="submit">Submit response</button>@endif</form></div>
@endsection
