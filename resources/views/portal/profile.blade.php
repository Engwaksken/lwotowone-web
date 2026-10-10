@extends('layout')
@section('title','Learner profile | Lwotowone')
@section('content')
<div class="page-wrap">
<span class="eyebrow">Learner onboarding</span>
<h1 class="page-title">Your profile</h1>
<p class="page-intro">Fill in your details and choose a course. Your cohort, enrollment category, date and learner number are assigned after payment is confirmed.</p>
@if($user->role!=='participant')
<form class="panel profile-form" method="post" action="/profile">@csrf
<div class="field"><label for="profile-name">Full name</label><input id="profile-name" name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field"><label>Email address</label><input value="{{ $user->email }}" disabled></div>
<div class="field"><label for="profile-phone">Phone number</label><input id="profile-phone" name="phone" value="{{ old('phone',$user->phone) }}"></div>
<div class="field"><label for="profile-district">District</label><input id="profile-district" name="district" value="{{ old('district',$user->district) }}"></div>
<div class="field"><label for="profile-expertise">Area of experience or interest</label><input id="profile-expertise" name="expertise" value="{{ old('expertise',$user->expertise) }}"></div>
<div class="field"><label for="profile-bio">About me</label><textarea id="profile-bio" name="bio">{{ old('bio',$user->bio) }}</textarea></div>
<button type="submit">Save profile</button></form>
@else
<form class="panel profile-form" method="post" action="/profile" data-learner-profile novalidate>@csrf
@php $refugee=(string)old('refugee',(int)$user->refugee)==='1';$pwd=(string)old('pwd',(int)$user->pwd)==='1';$employed=(string)old('employed',(int)$user->employed)==='1'; @endphp
<div class="tabs" data-tabs>
<div class="tab-list" role="tablist" aria-label="Learner profile sections">
<button type="button" role="tab" id="profile-tab-personal" aria-controls="profile-panel-personal" aria-selected="true">Personal and course</button>
<button type="button" role="tab" id="profile-tab-background" aria-controls="profile-panel-background" aria-selected="false" tabindex="-1">Background</button>
<button type="button" role="tab" id="profile-tab-goals" aria-controls="profile-panel-goals" aria-selected="false" tabindex="-1">Employment and goals</button>
</div>
<section class="tab-panel" role="tabpanel" id="profile-panel-personal" aria-labelledby="profile-tab-personal">
<div class="grid">
<div class="field"><label for="profile-name">Full name</label><input id="profile-name" name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field"><label>Email address</label><input value="{{ $user->email }}" disabled></div>
<div class="field"><label for="profile-phone">Phone</label><input id="profile-phone" type="tel" name="phone" value="{{ old('phone',$user->phone) }}" required></div>
<div class="field"><label for="profile-course">Course selection</label>@if($user->learning_access_paid)<input type="hidden" name="selected_course_id" value="{{ $user->selected_course_id }}"><input id="profile-course" value="{{ $courses->firstWhere('id',$user->selected_course_id)?->title??'Assigned course' }}" disabled><small>Contact your administrator to change this course.</small>@else<select id="profile-course" name="selected_course_id" required><option value="">Choose a course</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string)old('selected_course_id',$user->selected_course_id)===(string)$course->id)>{{ $course->title }}</option>@endforeach</select>@endif</div>
<div class="field"><label for="profile-gender">Gender</label><select id="profile-gender" name="gender" required><option value="">Select</option>@foreach(['Female','Male','Other','Prefer not to say'] as $gender)<option @selected(old('gender',$user->gender)===$gender)>{{ $gender }}</option>@endforeach</select></div>
</div></section>
<section class="tab-panel" role="tabpanel" id="profile-panel-background" aria-labelledby="profile-tab-background" hidden>
<div class="grid">
<div class="field"><label for="profile-location">Location</label><input id="profile-location" name="location" value="{{ old('location',$user->location) }}" required></div>
<div class="field"><label for="profile-urban-rural">Urban/Rural</label><select id="profile-urban-rural" name="urban_rural" required><option value="">Select</option>@foreach(['Urban','Rural'] as $area)<option @selected(old('urban_rural',$user->urban_rural)===$area)>{{ $area }}</option>@endforeach</select></div>
<div class="field"><label for="profile-age">Age of learner</label><input id="profile-age" type="number" name="learner_age" min="10" max="100" value="{{ old('learner_age',$user->learner_age) }}" required></div>
<div class="field"><label for="profile-refugee">Refugee</label><select id="profile-refugee" name="refugee" data-toggle="refugee" required><option value="0" @selected(!$refugee)>No</option><option value="1" @selected($refugee)>Yes</option></select></div>
<div class="field" data-dependent="refugee" @if(!$refugee)hidden @endif><label for="profile-settlement">Settlement</label><select id="profile-settlement" name="settlement_id" @if($refugee)required @endif><option value="">Select settlement</option>@foreach($settlements as $settlement)<option value="{{ $settlement->id }}" @selected((string)old('settlement_id')===(string)$settlement->id || ($user->settlement===$settlement->name && !old('settlement_id')))>{{ $settlement->name }}</option>@endforeach</select></div>
<div class="field"><label for="profile-pwd">Person with disability (PWD)</label><select id="profile-pwd" name="pwd" data-toggle="pwd" required><option value="0" @selected(!$pwd)>No</option><option value="1" @selected($pwd)>Yes</option></select></div>
<div class="field" data-dependent="pwd" @if(!$pwd)hidden @endif><label for="profile-impairment">Impairment type</label><select id="profile-impairment" name="impairment" @if($pwd)required @endif><option value="">Select impairment</option>@foreach(['Physical','Visual','Hearing','Speech','Intellectual','Psychosocial','Multiple','Other'] as $impairment)<option @selected(old('impairment',$user->impairment)===$impairment)>{{ $impairment }}</option>@endforeach</select></div>
<div class="field"><label for="profile-education">Education level</label><select id="profile-education" name="education_level" required><option value="">Select education level</option>@foreach(['No formal education','Primary','O-Level','A-Level','Certificate','Diploma',"Bachelor's degree",'Postgraduate','Other'] as $level)<option @selected(old('education_level',$user->education_level)===$level)>{{ $level }}</option>@endforeach</select></div>
</div></section>
<section class="tab-panel" role="tabpanel" id="profile-panel-goals" aria-labelledby="profile-tab-goals" hidden>
<div class="grid">
<div class="field"><label for="profile-employed">Are you employed?</label><select id="profile-employed" name="employed" data-toggle="employed" required><option value="0" @selected(!$employed)>No</option><option value="1" @selected($employed)>Yes</option></select></div>
<div class="field" data-dependent="employed" @if(!$employed)hidden @endif><label for="profile-employer">Employer name</label><input id="profile-employer" name="employer_name" value="{{ old('employer_name',$user->employer_name) }}" @if($employed)required @endif maxlength="255"></div>
<div class="field"><label for="profile-objective">Transformation objective</label><textarea id="profile-objective" name="transformation_objective">{{ old('transformation_objective',$user->transformation_objective) }}</textarea></div>
</div></section>
</div>
@if($user->profile_complete)<div class="panel learner-enrollment-summary"><h2>Enrollment details</h2><p><strong>Learner number:</strong> {{ $user->learner_no??'Assigned after payment is confirmed' }}</p><p><strong>Enrollment category:</strong> {{ $user->enrollment_category??'Set by your cohort' }}</p><p><strong>Enrollment date:</strong> {{ $user->enrollment_date??'Set when payment is confirmed' }}</p></div>@endif
<div class="profile-actions"><button type="submit">Save learner profile</button></div></form>
@if($user->profile_complete)<p class="notice success">Profile complete. Learning access: {{ $user->learning_access_paid?'Payment confirmed':'Payment required' }}.</p>@endif
@endif
</div>
@endsection
