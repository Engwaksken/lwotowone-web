@extends('layout')
@section('title','MEL reports | Lwotowone')
@section('content')
<div class="page-wrap">
@php
    $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
    $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
    $attributesOf = fn ($row) => is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
    $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
        ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
        ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
        ->values()->all();
@endphp
<span class="eyebrow">Monitoring, evaluation and learning</span>
<h1 class="page-title">MEL reports</h1>
<p class="page-intro">A snapshot of reach, inclusion and employment. Use the tabs to open a report.</p>
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
            <section class="mel-chart"><h2>Participation and inclusion</h2><p>Key measures from learner profiles.</p><div class="mel-bar-list">@foreach($inclusionChart as $item)<div class="mel-bar-row"><span>{{ $item['label'] }}</span><div class="mel-bar-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['value'] }}"><div class="mel-bar-fill" style="width:{{ min(100,round($item['value']*100/$maxInclusion)) }}%"></div></div><strong>{{ $item['value'] }}</strong></div>@endforeach</div></section>
        </div>
        <section class="panel mel-report-note"><h2>Programme reporting</h2><p>Use Learner indicators for profile and outcome data. Use Programme records for teacher, school, finance, partnership and revenue data.</p><a class="button small" href="/admin/enrollment">Go to enrollment</a></section>
    </section>
    <section class="tab-panel" role="tabpanel" id="mel-panel-learners" aria-labelledby="mel-tab-learners" hidden>
        <section class="panel"><div class="mel-panel-heading"><div><h2>Learner indicators</h2><p>Latest learner profile and outcomes data.</p></div><span class="tag">{{ count($learners) }} records shown</span></div><div class="table-wrap"><table><thead><tr><th>Learner number</th><th>Name</th><th>Gender</th><th>Age</th><th>Location</th><th>Refugee</th><th>PWD</th><th>Education</th><th>Enrolled</th><th>Employment</th><th>After-work status</th><th>Verified outcomes</th><th>Actions</th></tr></thead><tbody>
        @forelse($learners as $learner)<tr><td>{{ $learner->learner_no??'Pending' }}</td><td>{{ $learner->name }}</td><td>{{ $learner->gender }}</td><td>{{ $learner->learner_age }}</td><td>{{ $learner->location }}</td><td>{{ $learner->refugee?'Yes':'No' }}</td><td>{{ $learner->pwd?'Yes':'No' }}</td><td>{{ $learner->education_level }}</td><td>{{ $learner->learning_access_paid?'Yes':'No' }}</td><td>{{ $learner->employed?'Yes':'No' }}</td><td>{{ $learner->after_work_status }}</td><td>{{ \Illuminate\Support\Str::limit($learner->verified_outcomes??$learner->other_verified_outcome,120) }}</td><td><x-record-view-trigger :dialogId="'view-learner-'.$learner->id" :name="$learner->name" /><x-record-view-dialog :id="'view-learner-'.$learner->id" :title="$learner->name" :fields="array_merge([['label'=>'Learner number','value'=>$learner->learner_no??'Pending'],['label'=>'Name','value'=>$learner->name],['label'=>'Gender','value'=>$learner->gender],['label'=>'Age','value'=>$learner->learner_age],['label'=>'Location','value'=>$learner->location],['label'=>'Refugee','value'=>(bool) $learner->refugee],['label'=>'PWD','value'=>(bool) $learner->pwd],['label'=>'Education','value'=>$learner->education_level],['label'=>'Enrolled','value'=>(bool) $learner->learning_access_paid],['label'=>'Employed','value'=>(bool) $learner->employed],['label'=>'After-work status','value'=>$learner->after_work_status],['label'=>'Verified outcomes','value'=>$learner->verified_outcomes],['label'=>'Other verified outcome','value'=>$learner->other_verified_outcome]], $moreDetails($attributesOf($learner), ['learner_no','name','gender','learner_age','location','refugee','pwd','education_level','learning_access_paid','employed','after_work_status','verified_outcomes','other_verified_outcome']))" /></td></tr>@empty<tr><td colspan="13">No learner records yet.</td></tr>@endforelse
        </tbody></table></div>@if($learners->count()>=500)<p class="filter-help">Showing the 500 most recently updated learner profiles.</p>@endif</section>
    </section>
    <section class="tab-panel" role="tabpanel" id="mel-panel-data" aria-labelledby="mel-tab-data" hidden>
        <div class="tabs mel-data-tabs" data-tabs><div class="tab-list" role="tablist" aria-label="Programme indicator categories">
            @foreach($categories as $key=>$meta)<button type="button" role="tab" id="data-tab-{{ $key }}" aria-controls="data-panel-{{ $key }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $meta[0] }} <span class="tab-count">{{ $records->get($key,collect())->count() }}</span></button>@endforeach
        </div>
        @foreach($categories as $key=>$meta)
            <section class="tab-panel" role="tabpanel" id="data-panel-{{ $key }}" aria-labelledby="data-tab-{{ $key }}" @if(!$loop->first)hidden @endif>
                <section class="panel"><div class="mel-panel-heading"><div><h2>{{ $meta[0] }}</h2><p>Recorded indicator data for reporting.</p></div><span class="tag">{{ $records->get($key,collect())->count() }} entries</span></div><div class="table-wrap"><table><thead><tr>@foreach(array_slice($meta,1) as [$field,$label])<th>{{ $label }}</th>@endforeach<th>Recorded</th><th>Actions</th></tr></thead><tbody>
                @forelse($records->get($key,collect()) as $record)@php $values=(array)json_decode($record->data,true); $viewFields=[]; foreach(array_slice($meta,1) as [$field,$label]){$viewFields[]=['label'=>$label,'value'=>$values[$field]??null];} $viewFields[]=['label'=>'Recorded','value'=>\Illuminate\Support\Carbon::parse($record->created_at)->format('d M Y')]; $recordName=$viewFields[0]['value']?:('Record '.$record->id); $viewFields=array_merge($viewFields,$moreDetails($attributesOf($record),['data','created_at'])); @endphp<tr>@foreach(array_slice($meta,1) as [$field])<td>{{ $values[$field]??'—' }}</td>@endforeach<td>{{ \Illuminate\Support\Carbon::parse($record->created_at)->format('d M Y') }}</td><td><x-record-view-trigger :dialogId="'view-record-'.$key.'-'.$record->id" :name="$recordName" /><x-record-view-dialog :id="'view-record-'.$key.'-'.$record->id" :title="$recordName" :fields="$viewFields" /></td></tr>@empty<tr><td colspan="{{ count($meta)+1 }}">No {{ strtolower($meta[0]) }} records yet.</td></tr>@endforelse
                </tbody></table></div></section>
            </section>
        @endforeach
        </div>
    </section>
</div>
</div>
@endsection
