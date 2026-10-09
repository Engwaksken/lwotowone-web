@extends('layout')
@section('title','Learning access payment | Lwotowone')
@section('content')
<span class="eyebrow">Learning access</span><h1>Payment confirmation</h1>
@if(!$user->profile_complete)<p>Please <a href="/profile">complete your learner profile</a> before requesting learning access.</p>
@elseif($user->learning_access_paid)<div class="notice success">Your payment has been confirmed. <a href="/portal/learn">Access learning materials</a>.</div>
@else<div class="panel"><h2>Payment required</h2><p>Your learner profile is complete. Selected course: <strong>{{ \Illuminate\Support\Facades\DB::table('courses')->where('id',$user->selected_course_id)->value('title')??'Not selected' }}</strong>.</p><p>Pay using one of the active methods below, then contact your programme coordinator to confirm payment. Once verified, your cohort, enrollment category and date, and learner number will be assigned automatically, and course access will be activated.</p>
@forelse($gateways as $gateway)
<article class="panel"><h3>{{ $gateway->name }}</h3><p><strong>{{ $gateway->provider_type }}</strong>@if($gateway->provider) · {{ $gateway->provider }}@endif</p>
@if($gateway->amount)<p><strong>Amount:</strong> {{ number_format($gateway->amount,2) }} {{ $gateway->currency }}</p>@endif
@if($gateway->account_name)<p><strong>Account holder:</strong> {{ $gateway->account_name }}</p>@endif
@if($gateway->account_number)<p><strong>Account / phone:</strong> {{ $gateway->account_number }}</p>@endif
@if($gateway->merchant_code)<p><strong>Merchant code:</strong> {{ $gateway->merchant_code }}</p>@endif
@include('partials.payment-details')
@if($gateway->instructions)<p class="prose">{{ $gateway->instructions }}</p>@endif</article>
@empty<p>No payment methods are available yet. Please contact your programme coordinator.</p>@endforelse
<p class="muted">Access status: Awaiting payment confirmation.</p></div>@endif
@endsection
