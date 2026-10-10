<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Events">
        <button type="button" role="tab" id="tab-upcoming-events" aria-controls="panel-upcoming-events" aria-selected="true">Upcoming events <span class="tab-count">{{ count($data['events']) }}</span></button>
        <button type="button" role="tab" id="tab-event-history" aria-controls="panel-event-history" aria-selected="false" tabindex="-1">My registrations <span class="tab-count">{{ count($data['event_registrations']) }}</span></button>
    </div>
    <section class="tab-panel" role="tabpanel" id="panel-upcoming-events" aria-labelledby="tab-upcoming-events">
        <div class="grid">
        @forelse($data['events'] as $event)
            @php $registration=collect($data['event_registrations'])->firstWhere('event_id',$event->id); @endphp
            <article class="panel"><h3>{{ $event->title }}</h3><p>{{ $event->description }}</p><small>{{ $event->starts_at }} · {{ $event->location }}</small>
                @if($registration)<p class="tag">{{ ucfirst($registration->status) }}</p>@endif
                @if(!$registration||$registration->status==='cancelled')
                    @if(now()->lt($event->starts_at))<form method="post" action="/actions/register-event">@csrf<input type="hidden" name="event_id" value="{{ $event->id }}"><button>{{ $registration?'Register again':'Register for event' }}</button></form>@else<p class="muted">Registration is closed.</p>@endif
                @elseif($registration->can_cancel)
                    <form method="post" action="/actions/cancel-event" data-confirm="Cancel your registration? Your place will open up for another learner.">@csrf<input type="hidden" name="registration_id" value="{{ $registration->id }}"><button class="secondary">Cancel registration</button></form>
                @endif
            </article>
        @empty<div class="empty">No events right now. Check back soon.</div>
        @endforelse
        </div>
    </section>
    <section class="tab-panel" role="tabpanel" id="panel-event-history" aria-labelledby="tab-event-history">
        @forelse($data['event_registrations'] as $registration)
            <article class="panel"><strong>{{ $registration->event_title }}</strong><p>{{ ucfirst($registration->status) }}</p>
                @if($registration->can_cancel&&!collect($data['events'])->contains('id',$registration->event_id))<form method="post" action="/actions/cancel-event" data-confirm="Cancel your registration?">@csrf<input type="hidden" name="registration_id" value="{{ $registration->id }}"><button class="secondary">Cancel registration</button></form>@endif
            </article>
        @empty<p class="muted">You have not registered for any events yet.</p>
        @endforelse
    </section>
</div>
