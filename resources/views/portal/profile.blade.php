@extends('layout')
@section('title','Learner profile | Lwotowone')
@section('content')
<span class="eyebrow">Learner onboarding</span><h1>Finalize your learner profile</h1>
<p>Complete these details after signup. They help us understand learner reach and outcomes.</p>
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
<form class="panel profile-form" method="post" action="/profile">@csrf
<div class="grid">
@php $fields=[['learner_no','Learner number','text'],['venture','Venture','text'],['enrollment_date','Enrollment date','date'],['enrollment_category','Enrollment category','text'],['gender','Gender','select',['Female','Male','Other','Prefer not to say']],['phone','Phone','tel'],['location','Location','text'],['urban_rural','Urban/Rural','select',['Urban','Rural']],['learner_age','Age of learner','number'],['refugee','Refugee','select',['0'=>'No','1'=>'Yes']],['settlement','Settlement','text'],['pwd','PWD','select',['0'=>'No','1'=>'Yes']],['impairment','Impairment','text'],['education_level','Education level','text'],['learner_status','Learner status','text'],['verified_outcomes','Verified learner outcomes','textarea'],['other_verified_outcome','Other verified learner outcome','textarea'],['yiw_before','Youth in Work before','text'],['transformation_objective','Transformation objective','textarea'],['after_work_status','After-work status','text'],['after_work_pathway','After-work pathway','text']]; @endphp
@foreach($fields as $field)@php [$key,$label,$type]=$field; $choices=$field[3]??[]; @endphp
<div class="field"><label for="profile-{{ $key }}">{{ $label }}</label>
@if($type==='select')<select id="profile-{{ $key }}" name="{{ $key }}" @if(!in_array($key,['settlement']))required @endif><option value="">Select</option>@foreach($choices as $value=>$choice)@php $booleanField=in_array($key,['refugee','pwd'],true);$optionValue=$booleanField?(string)(int)($choice==='Yes'):(is_int($value)?$choice:$value);$selectedValue=old($key,$user->$key);if($booleanField&&$selectedValue!==null)$selectedValue=(string)(int)(bool)$selectedValue; @endphp<option value="{{ $optionValue }}" @selected((string)$selectedValue===(string)$optionValue)>{{ $choice }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea id="profile-{{ $key }}" name="{{ $key }}">{{ old($key,$user->$key) }}</textarea>
@else<input id="profile-{{ $key }}" type="{{ $type }}" name="{{ $key }}" value="{{ old($key,$user->$key) }}" @if(!in_array($key,['learner_no','venture','enrollment_date']))required @endif @if($type==='number')min="10" max="100"@endif>@endif</div>
@endforeach
<div class="field"><label for="profile-name">Full name</label><input id="profile-name" name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field"><label>Email address</label><input value="{{ $user->email }}" disabled></div>
</div><div class="profile-actions"><button type="submit">Save learner profile</button></div></form>
@if($user->profile_complete)<p class="notice success">Profile complete. Learning access: {{ $user->learning_access_paid?'Payment confirmed':'Payment required' }}.</p>@endif
@endif
@endsection
