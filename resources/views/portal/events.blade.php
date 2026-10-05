@forelse($data['events'] as $event)
@php $registration=collect($data['event_registrations'])->firstWhere('event_id',$event->id); @endphp
<article class="panel"><h3>{{ $event->title }}</h3><p>{{ $event->description }}</p><small>{{ $event->starts_at }} · {{ $event->location }}</small>
@if($registration)<p class="tag">{{ ucfirst($registration->status) }}</p>@endif
@if(!$registration||$registration->status==='cancelled')
@if(now()->lt($event->starts_at))<form method="post" action="/actions/register-event">@csrf<input type="hidden" name="event_id" value="{{ $event->id }}"><button>{{ $registration?'Register again':'Register for event' }}</button></form>@else<p class="muted">Registration is closed.</p>@endif
@elseif($registration->can_cancel)
<form method="post" action="/actions/cancel-event" data-confirm="Cancel your registration? Your place will become available to another learner.">@csrf<input type="hidden" name="registration_id" value="{{ $registration->id }}"><button class="secondary">Cancel registration</button></form>
@endif</article>@empty<div class="empty">No published events.</div>@endforelse
<h2>My event history</h2>@forelse($data['event_registrations'] as $registration)<p>{{ $registration->event_title }} · {{ ucfirst($registration->status) }}</p>@if($registration->can_cancel&&!collect($data['events'])->contains('id',$registration->event_id))<form method="post" action="/actions/cancel-event" data-confirm="Cancel your registration?">@csrf<input type="hidden" name="registration_id" value="{{ $registration->id }}"><button class="secondary">Cancel registration</button></form>@endif @empty<p class="muted">You have no event registrations yet.</p>@endforelse
