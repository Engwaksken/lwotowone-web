@props(['statistics'])
<section class="stats survey-statistics" aria-label="Survey statistics">
    <div class="stat"><span class="stat-icon"><i class="fas fa-poll" aria-hidden="true"></i></span><strong>{{ number_format($statistics['total']) }}</strong><span>Total surveys</span><small>All surveys, across all pages</small></div>
    <div class="stat"><span class="stat-icon"><i class="fas fa-link" aria-hidden="true"></i></span><strong>{{ number_format($statistics['open']) }}</strong><span>Open for responses</span><small>Published and within the opening window</small></div>
    <div class="stat"><span class="stat-icon"><i class="fas fa-pen" aria-hidden="true"></i></span><strong>{{ number_format($statistics['drafts']) }}</strong><span>Draft surveys</span><small>Not yet collecting responses</small></div>
    <div class="stat"><span class="stat-icon"><i class="fas fa-comments" aria-hidden="true"></i></span><strong>{{ number_format($statistics['responses']) }}</strong><span>Total responses</span><small>{{ number_format($statistics['anonymous_responses']) }} anonymous · {{ number_format($statistics['responses']-$statistics['anonymous_responses']) }} named</small></div>
</section>
