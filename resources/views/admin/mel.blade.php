@extends('layout')
@section('title','MEL reports | Lwotowone')
@section('content')
<span class="eyebrow">Monitoring, evaluation and learning</span>
<h1>MEL reports</h1>
<p class="mel-intro">A snapshot of learner reach, inclusion, employment and programme indicators. Use the tabs to focus on the report you need.</p>
<div class="stats mel-stats">
    @foreach($counts as $label=>$value)
        <div class="stat" data-stat-index="{{ $loop->index }}"><span class="stat-icon"><i class="fas {{ str_contains(strtolower($label),'learner')?'fa-users':(str_contains(strtolower($label),'refugee')?'fa-globe-africa':(str_contains(strtolower($label),'pwd')?'fa-universal-access':(str_contains(strtolower($label),'school')?'fa-school':(str_contains(strtolower($label),'finance')||str_contains(strtolower($label),'revenue')?'fa-coins':(str_contains(strtolower($label),'partner')?'fa-handshake':(str_contains(strtolower($label),'work')||str_contains(strtolower($label),'employ')?'fa-briefcase':'fa-chart-line')))))) }}" aria-hidden="true"></i></span><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
    @endforeach
</div>

<div class="tabs mel-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="MEL report sections">
        <button type="button" role="tab" id="mel-tab-overview" aria-controls="mel-panel-overview" aria-selected="true">Overview</button>
        <button type="button" role="tab" id="mel-tab-learners" aria-controls="mel-panel-learners" aria-selected="false" tabindex="-1">Learner indicators</button>
        <button type="button" role="tab" id="mel-tab-data" aria-controls="mel-panel-data" aria-selected="false" tabindex="-1">Programme records</button>
    </div>
    <section class="tab-panel" role="tabpanel" id="mel-panel-overview" aria-labelledby="mel-tab-overview">
        @php
            $genderTotal=array_sum(array_column($genderChart,'value'));
            $pieColors=['#175742','#d09a31','#557fa1','#a45b58','#8064a2'];
            $pieOffset=0;$pieParts=[];
            foreach($genderChart as $i=>$slice){$portion=$genderTotal?($slice['value']/$genderTotal)*100:0;$pieParts[]=$pieColors[$i%count($pieColors)].' '.$pieOffset.'% '.($pieOffset+$portion).'%';$pieOffset+=$portion;}
            $pieGradient=$pieParts?implode(',',$pieParts):'#e7ece8 0% 100%';
            $maxInclusion=max(1,...array_column($inclusionChart,'value'));
        @endphp
        <div class="mel-chart-grid">
            <section class="mel-chart"><h2>Learner gender distribution</h2><p>Profile data recorded for enrolled learners.</p><div class="mel-pie-layout"><div class="mel-pie" role="img" aria-label="Learner gender distribution chart" data-total="{{ $genderTotal }} learners" style="--pie-gradient:conic-gradient({{ $pieGradient }})"></div><ul class="mel-legend">@forelse($genderChart as $i=>$slice)<li><i style="--legend-color:{{ $pieColors[$i%count($pieColors)] }}"></i>{{ $slice['label'] }} <strong>{{ $slice['value'] }}</strong></li>@empty<li>No learner profile data yet.</li>@endforelse</ul></div></section>
            <section class="mel-chart"><h2>Participation and inclusion</h2><p>Selected measures from learner profiles.</p><div class="mel-bar-list">@foreach($inclusionChart as $item)<div class="mel-bar-row"><span>{{ $item['label'] }}</span><div class="mel-bar-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['value'] }}"><div class="mel-bar-fill" style="width:{{ min(100,round($item['value']*100/$maxInclusion)) }}%"></div></div><strong>{{ $item['value'] }}</strong></div>@endforeach</div></section>
        </div>
        <section class="panel mel-report-note"><h2>Programme reporting</h2><p>Use Learner indicators for profile and outcome records, or Programme records for disaggregated teacher, school, finance, partnership and revenue data.</p><a class="button small" href="/admin/enrollment">Open enrollment management</a></section>
    </section>
    <section class="tab-panel" role="tabpanel" id="mel-panel-learners" aria-labelledby="mel-tab-learners" hidden>
        <section class="panel"><div class="mel-panel-heading"><div><h2>Learner indicators</h2><p>Latest learner profile and outcomes data.</p></div><span class="tag">{{ count($learners) }} records shown</span></div><div class="table-wrap"><table><thead><tr><th>Learner number</th><th>Name</th><th>Gender</th><th>Age</th><th>Location</th><th>Refugee</th><th>PWD</th><th>Education</th><th>Enrolled</th><th>Employment</th><th>After-work status</th><th>Verified outcomes</th></tr></thead><tbody>
        @forelse($learners as $learner)<tr><td>{{ $learner->learner_no??'Pending' }}</td><td>{{ $learner->name }}</td><td>{{ $learner->gender }}</td><td>{{ $learner->learner_age }}</td><td>{{ $learner->location }}</td><td>{{ $learner->refugee?'Yes':'No' }}</td><td>{{ $learner->pwd?'Yes':'No' }}</td><td>{{ $learner->education_level }}</td><td>{{ $learner->learning_access_paid?'Yes':'No' }}</td><td>{{ $learner->employed?'Yes':'No' }}</td><td>{{ $learner->after_work_status }}</td><td>{{ \Illuminate\Support\Str::limit($learner->verified_outcomes??$learner->other_verified_outcome,120) }}</td></tr>@empty<tr><td colspan="12">No learner records yet.</td></tr>@endforelse
        </tbody></table></div>@if($learners->count()>=500)<p class="filter-help">Showing the 500 most recently updated learner profiles.</p>@endif</section>
    </section>
    <section class="tab-panel" role="tabpanel" id="mel-panel-data" aria-labelledby="mel-tab-data" hidden>
        <div class="tabs mel-data-tabs" data-tabs><div class="tab-list" role="tablist" aria-label="Programme indicator categories">
            @foreach($categories as $key=>$meta)<button type="button" role="tab" id="data-tab-{{ $key }}" aria-controls="data-panel-{{ $key }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $meta[0] }} <span class="tab-count">{{ $records->get($key,collect())->count() }}</span></button>@endforeach
        </div>
        @foreach($categories as $key=>$meta)
            <section class="tab-panel" role="tabpanel" id="data-panel-{{ $key }}" aria-labelledby="data-tab-{{ $key }}" @if(!$loop->first)hidden @endif>
                <section class="panel"><div class="mel-panel-heading"><div><h2>{{ $meta[0] }}</h2><p>Recorded indicator data for reporting.</p></div><span class="tag">{{ $records->get($key,collect())->count() }} entries</span></div><div class="table-wrap"><table><thead><tr>@foreach(array_slice($meta,1) as [$field,$label])<th>{{ $label }}</th>@endforeach<th>Recorded</th></tr></thead><tbody>
                @forelse($records->get($key,collect()) as $record)@php $values=(array)json_decode($record->data,true); @endphp<tr>@foreach(array_slice($meta,1) as [$field])<td>{{ $values[$field]??'—' }}</td>@endforeach<td>{{ \Illuminate\Support\Carbon::parse($record->created_at)->format('d M Y') }}</td></tr>@empty<tr><td colspan="{{ count($meta)+1 }}">No {{ strtolower($meta[0]) }} indicator records have been added yet.</td></tr>@endforelse
                </tbody></table></div></section>
            </section>
        @endforeach
        </div>
    </section>
</div>
@endsection
