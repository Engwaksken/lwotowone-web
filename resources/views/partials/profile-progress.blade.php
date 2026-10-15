@php
    $progressUser=$progressUser??auth()->user();
    $profilePercent=\App\Services\ProfileProgress::percent($progressUser);
    $profileMissing=\App\Services\ProfileProgress::missing($progressUser);
@endphp
<section class="profile-progress {{ $profilePercent===100?'is-complete':'' }}" aria-labelledby="profile-progress-title" data-profile-progress>
    <div class="profile-progress-head">
        <h2 id="profile-progress-title">@if($profilePercent===100)<i class="fas fa-check-circle" aria-hidden="true"></i> Profile complete @else Complete your profile to unlock learning @endif</h2>
        <strong class="profile-progress-value" data-profile-progress-value>{{ $profilePercent }}%</strong>
    </div>
    <div class="profile-progress-track" role="progressbar" aria-label="Profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $profilePercent }}" data-profile-progress-bar><span style="width:{{ $profilePercent }}%"></span></div>
    @if($profilePercent<100)
        <p class="profile-progress-note" data-profile-progress-note>Courses, lessons, resources, events and opportunities open once your profile is complete.@if($profileMissing) Still needed: {{ implode(', ',$profileMissing) }}.@endif</p>
        @if(!($onProfilePage??false))<a class="button small" href="/profile">Complete my profile</a>@endif
    @endif
</section>
