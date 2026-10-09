@extends('layout')
@section('content')<a href="/calls">All open calls</a><h1>{{ $call->title }}</h1><p class="prose">{{ $call->description }}</p><p>Applications: {{ $call->opens_at }} – {{ $call->closes_at }}</p>
@if($application)<div class="panel"><strong>Application: {{ $application->status }}</strong><p>{{ $application->feedback }}</p>@if($application->status==='approved'&&$call->course_id)<a class="button" href="/learning/{{ $call->course_id }}">Open your course</a>@endif</div>
@elseif(now()->gte($call->opens_at)&&now()->lt($call->closes_at))
@guest<a class="button" href="/calls/{{ $call->token }}/apply">Sign in to apply</a><p><a href="/register">Create an account</a> if you are new.</p>@else
@if(auth()->user()->role==='participant')<form class="panel" method="post" action="/calls/{{ $call->token }}/apply">@csrf<div class="field"><label for="call-motivation">Why would you like to participate?</label><textarea id="call-motivation" name="motivation" required maxlength="10000"></textarea></div><button type="submit">Submit application for M&E review</button></form>@endif
@endguest
@else<p>Applications are not currently open.</p>@endif
@endsection
