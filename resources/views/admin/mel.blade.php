@extends('layout')
@section('title','M&E dashboard | Lwotowone')
@section('content')
<div class="page-wrap mel-page">
@php
    $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
    $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
    $attributesOf = fn ($row) => is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
    $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
        ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
        ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
        ->values()->all();
    $iconFor = function (string $label): string {
        $label = strtolower($label);
        return match (true) {
            str_contains($label, 'learner') => 'fa-users',
            str_contains($label, 'refugee') => 'fa-globe-africa',
            str_contains($label, 'pwd') => 'fa-universal-access',
            str_contains($label, 'school') => 'fa-school',
            str_contains($label, 'teacher') => 'fa-chalkboard-teacher',
            str_contains($label, 'finance') || str_contains($label, 'revenue') => 'fa-coins',
            str_contains($label, 'partner') => 'fa-handshake',
            str_contains($label, 'work') || str_contains($label, 'employ') => 'fa-briefcase',
            default => 'fa-chart-line',
        };
    };
    $genderTotal = array_sum(array_column($genderChart, 'value'));
    $pieColors = ['#175742', '#d09a31', '#557fa1', '#a45b58', '#8064a2'];
    $pieOffset = 0; $pieParts = [];
    foreach ($genderChart as $i => $slice) { $portion = $genderTotal ? ($slice['value'] / $genderTotal) * 100 : 0; $pieParts[] = $pieColors[$i % count($pieColors)].' '.$pieOffset.'% '.($pieOffset + $portion).'%'; $pieOffset += $portion; }
    $pieGradient = $pieParts ? implode(',', $pieParts) : '#e7ece8 0% 100%';
    $maxInclusion = max(1, ...array_column($inclusionChart, 'value'));
    $gapTotal = array_sum(array_column($quality, 'value'));
@endphp

<div class="dash-head">
    <div>
        <span class="eyebrow">Monitoring, evaluation and learning</span>
        <h1 class="page-title">M&amp;E dashboard</h1>
    </div>
    <div class="dash-head-links">
        <a class="button secondary small" href="/admin/reports/impact.csv"><i class="fas fa-file-export" aria-hidden="true"></i> Export impact report</a>
        <a class="button secondary small" href="/admin/enrollment">Enrollment and cohorts</a>
    </div>
</div>
<p class="page-intro">Check reach and outcomes, fix data gaps, and add the records donors and partners ask for.</p>

<section class="stats mel-stats" aria-label="Scorecard">
    @foreach($counts as $label=>$value)
        <div class="stat" data-stat-index="{{ $loop->index }}"><span class="stat-icon"><i class="fas {{ $iconFor($label) }}" aria-hidden="true"></i></span><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
    @endforeach
</section>

<div class="mel-todo">
    <section class="panel" aria-labelledby="mel-gaps">
        <div class="mel-panel-heading">
            <div>
                <h2 id="mel-gaps">Fix data gaps</h2>
                <p>{{ $gapTotal === 0 ? 'No gaps found. Reporting data is complete.' : number_format($gapTotal).' items will make reports incomplete.' }}</p>
            </div>
        </div>
        <ul class="dash-list mel-gap-list">
            @foreach($quality as $gap)
                <li>
                    <span>
                        <strong>{{ $gap['label'] }}</strong>
                        <small>{{ $gap['hint'] }}</small>
                    </span>
                    <span class="dash-count">{{ number_format($gap['value']) }}</span>
                    <a class="button secondary small" href="{{ $gap['href'] ?? '/admin/mel#mel-panel-learners' }}">{{ ($gap['href'] ?? null) ? 'Open' : 'View learners' }}</a>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel" aria-labelledby="mel-add">
        <div class="mel-panel-heading">
            <div>
                <h2 id="mel-add">Add a record</h2>
                <p>Enter data from field visits, partners and finance.</p>
            </div>
        </div>
        <div class="mel-add-grid">
            @foreach($categories as $key=>$meta)
                <button type="button" class="secondary mel-add-button" data-dialog-open="mel-add-{{ $key }}">
                    <i class="fas {{ $iconFor($meta[0]) }}" aria-hidden="true"></i>
                    <span>{{ $meta[0] }}</span>
                    <small>{{ $records->get($key, collect())->count() }} saved</small>
                </button>
            @endforeach
        </div>
    </section>
</div>

<div class="tabs mel-tabs" data-tabs>
    <div class="tab-list" role="tablist" aria-label="M&amp;E sections">
        <button type="button" role="tab" id="mel-tab-overview" aria-controls="mel-panel-overview" aria-selected="true">Overview</button>
        <button type="button" role="tab" id="mel-tab-learners" aria-controls="mel-panel-learners" aria-selected="false" tabindex="-1">Learners</button>
        <button type="button" role="tab" id="mel-tab-data" aria-controls="mel-panel-data" aria-selected="false" tabindex="-1">Programme records</button>
        <button type="button" role="tab" id="mel-tab-documents" aria-controls="mel-panel-documents" aria-selected="false" tabindex="-1">Evidence documents</button>
        <button type="button" role="tab" id="mel-tab-surveys" aria-controls="mel-panel-surveys" aria-selected="false" tabindex="-1">Surveys</button>
    </div>

    <section class="tab-panel" role="tabpanel" id="mel-panel-overview" aria-labelledby="mel-tab-overview">
        <div class="mel-chart-grid">
            <section class="mel-chart"><h2>Learner gender</h2><p>From learner profiles.</p><div class="mel-pie-layout"><div class="mel-pie" role="img" aria-label="Learner gender distribution chart" data-total="{{ $genderTotal }} learners" style="--pie-gradient:conic-gradient({{ $pieGradient }})"></div><ul class="mel-legend">@forelse($genderChart as $i=>$slice)<li><i style="--legend-color:{{ $pieColors[$i%count($pieColors)] }}"></i>{{ $slice['label'] }} <strong>{{ $slice['value'] }}</strong></li>@empty<li>No learner profile data yet.</li>@endforelse</ul></div></section>
            <section class="mel-chart"><h2>Participation and inclusion</h2><p>Key measures from learner profiles.</p><div class="mel-bar-list">@foreach($inclusionChart as $item)<div class="mel-bar-row"><span>{{ $item['label'] }}</span><div class="mel-bar-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['value'] }}"><div class="mel-bar-fill" style="width:{{ min(100,round($item['value']*100/$maxInclusion)) }}%"></div></div><strong>{{ $item['value'] }}</strong></div>@endforeach</div></section>
        </div>
    </section>

    <section class="tab-panel" role="tabpanel" id="mel-panel-learners" aria-labelledby="mel-tab-learners" hidden>
        <section class="panel"><div class="mel-panel-heading"><div><h2>Learner records</h2><p>Latest learner profile and outcome data.</p></div><span class="tag">{{ count($learners) }} shown</span></div><div class="table-wrap"><table><thead><tr><th>Learner number</th><th>Name</th><th>Gender</th><th>Age</th><th>Location</th><th>Refugee</th><th>PWD</th><th>Education</th><th>Enrolled</th><th>Employment</th><th>After-work status</th><th>Verified outcomes</th><th>Actions</th></tr></thead><tbody>
        @forelse($learners as $learner)<tr><td>{{ $learner->learner_no??'Pending' }}</td><td>{{ $learner->name }}</td><td>{{ $learner->gender }}</td><td>{{ $learner->learner_age }}</td><td>{{ $learner->location }}</td><td>{{ $learner->refugee?'Yes':'No' }}</td><td>{{ $learner->pwd?'Yes':'No' }}</td><td>{{ $learner->education_level }}</td><td>{{ $learner->learning_access_paid?'Yes':'No' }}</td><td>{{ $learner->employed?'Yes':'No' }}</td><td>{{ $learner->after_work_status }}</td><td>{{ \Illuminate\Support\Str::limit($learner->verified_outcomes??$learner->other_verified_outcome,120) }}</td><td><x-record-view-trigger :dialogId="'view-learner-'.$learner->id" :name="$learner->name" /><x-record-view-dialog :id="'view-learner-'.$learner->id" :title="$learner->name" :fields="array_merge([['label'=>'Learner number','value'=>$learner->learner_no??'Pending'],['label'=>'Name','value'=>$learner->name],['label'=>'Gender','value'=>$learner->gender],['label'=>'Age','value'=>$learner->learner_age],['label'=>'Location','value'=>$learner->location],['label'=>'Refugee','value'=>(bool) $learner->refugee],['label'=>'PWD','value'=>(bool) $learner->pwd],['label'=>'Education','value'=>$learner->education_level],['label'=>'Enrolled','value'=>(bool) $learner->learning_access_paid],['label'=>'Employed','value'=>(bool) $learner->employed],['label'=>'After-work status','value'=>$learner->after_work_status],['label'=>'Verified outcomes','value'=>$learner->verified_outcomes],['label'=>'Other verified outcome','value'=>$learner->other_verified_outcome]], $moreDetails($attributesOf($learner), ['learner_no','name','gender','learner_age','location','refugee','pwd','education_level','learning_access_paid','employed','after_work_status','verified_outcomes','other_verified_outcome']))" /></td></tr>@empty<tr><td colspan="13">No learner records yet.</td></tr>@endforelse
        </tbody></table></div>@if($learners->count()>=500)<p class="filter-help">Showing the 500 most recently updated learner profiles.</p>@endif</section>
    </section>

    <section class="tab-panel" role="tabpanel" id="mel-panel-data" aria-labelledby="mel-tab-data" hidden>
        <div class="tabs mel-data-tabs" data-tabs><div class="tab-list" role="tablist" aria-label="Programme record categories">
            @foreach($categories as $key=>$meta)<button type="button" role="tab" id="data-tab-{{ $key }}" aria-controls="data-panel-{{ $key }}" aria-selected="{{ $loop->first?'true':'false' }}" @if(!$loop->first)tabindex="-1"@endif>{{ $meta[0] }} <span class="tab-count">{{ $records->get($key,collect())->count() }}</span></button>@endforeach
        </div>
        @foreach($categories as $key=>$meta)
            <section class="tab-panel" role="tabpanel" id="data-panel-{{ $key }}" aria-labelledby="data-tab-{{ $key }}" @if(!$loop->first)hidden @endif>
                <section class="panel"><div class="mel-panel-heading"><div><h2>{{ $meta[0] }}</h2><p>Recorded indicator data for reporting.</p></div><div class="dash-head-links"><span class="tag">{{ $records->get($key,collect())->count() }} entries</span><button type="button" class="small" data-dialog-open="mel-add-{{ $key }}"><i class="fas fa-plus" aria-hidden="true"></i> Add</button></div></div><div class="table-wrap"><table><thead><tr>@foreach(array_slice($meta,1) as [$field,$label])<th>{{ $label }}</th>@endforeach<th>Recorded</th><th>Actions</th></tr></thead><tbody>
                @forelse($records->get($key,collect()) as $record)@php $values=(array)json_decode($record->data,true); $viewFields=[]; foreach(array_slice($meta,1) as [$field,$label]){$viewFields[]=['label'=>$label,'value'=>$values[$field]??null];} $viewFields[]=['label'=>'Recorded','value'=>\Illuminate\Support\Carbon::parse($record->created_at)->format('d M Y')]; $recordName=$viewFields[0]['value']?:('Record '.$record->id); $viewFields=array_merge($viewFields,$moreDetails($attributesOf($record),['data','created_at'])); @endphp<tr>@foreach(array_slice($meta,1) as [$field])<td>{{ $values[$field]??'—' }}</td>@endforeach<td>{{ \Illuminate\Support\Carbon::parse($record->created_at)->format('d M Y') }}</td><td><div class="actions"><x-record-view-trigger :dialogId="'view-record-'.$key.'-'.$record->id" :name="$recordName" /><form method="post" action="/admin/mel/{{ $key }}/{{ $record->id }}" data-confirm="Delete this record?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete record" aria-label="Delete record"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form></div><x-record-view-dialog :id="'view-record-'.$key.'-'.$record->id" :title="$recordName" :fields="$viewFields" /></td></tr>@empty<tr><td colspan="{{ count($meta)+1 }}">No {{ strtolower($meta[0]) }} records yet. Use Add to enter the first one.</td></tr>@endforelse
                </tbody></table></div></section>
            </section>
        @endforeach
        </div>
    </section>

    <section class="tab-panel" role="tabpanel" id="mel-panel-documents" aria-labelledby="mel-tab-documents" hidden>
        <section class="panel">
            <div class="mel-panel-heading"><div><h2>Upload evidence</h2><p>Reports, signed forms and photos that back up the figures. PDF, Word, Excel, CSV or image files up to 20 MB.</p></div></div>
            <form class="mel-upload" method="post" action="/admin/mel/documents" enctype="multipart/form-data">
                @csrf
                <div class="field"><label for="mel-doc-category">Linked to</label>
                    <select id="mel-doc-category" name="category" required>
                        @foreach($categories as $key=>$meta)<option value="{{ $key }}">{{ $meta[0] }}</option>@endforeach
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="field"><label for="mel-doc-name">Document name</label><input id="mel-doc-name" name="document" type="text" maxlength="255" required></div>
                <div class="field"><label for="mel-doc-description">Description <span class="optional">(optional)</span></label><input id="mel-doc-description" name="description" type="text" maxlength="5000"></div>
                <div class="field"><label for="mel-doc-file">File</label><input id="mel-doc-file" name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg" required></div>
                <div class="mel-upload-action"><button type="submit"><i class="fas fa-upload" aria-hidden="true"></i> Upload</button></div>
            </form>
        </section>

        <section class="panel">
            <div class="mel-panel-heading"><div><h2>Uploaded evidence</h2><p>Most recent {{ count($documents) }} files.</p></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Document</th><th>Linked to</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($documents as $doc)
                    <tr>
                        <td><strong>{{ $doc->document }}</strong>@if($doc->description)<small>{{ $doc->description }}</small>@endif</td>
                        <td>{{ $categories[$doc->category][0] ?? ucfirst(str_replace('_',' ',$doc->category)) }}</td>
                        <td>{{ $doc->size ? number_format($doc->size / 1024, 0).' KB' : '—' }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($doc->created_at)->format('d M Y') }}</td>
                        <td><div class="actions">
                            <a class="button secondary small icon-action" href="/admin/mel/documents/{{ $doc->id }}/download" title="Download {{ $doc->document }}" aria-label="Download {{ $doc->document }}"><i class="fas fa-download" aria-hidden="true"></i></a>
                            <form method="post" action="/admin/mel/documents/{{ $doc->id }}" data-confirm="Delete this document?">@csrf @method('DELETE')<button class="danger small icon-action" type="submit" title="Delete document" aria-label="Delete {{ $doc->document }}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No evidence uploaded yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </section>
    <section class="tab-panel" role="tabpanel" id="mel-panel-surveys" aria-labelledby="mel-tab-surveys" hidden><div class="mel-panel-heading"><div><h2>MEL surveys</h2><p>Collect field feedback with named or anonymous forms, participant links and QR codes.</p></div><div class="actions"><button type="button" class="small" data-dialog-open="create-survey">Create survey</button><a class="button secondary small" href="/admin/mel/surveys">Manage all surveys</a></div></div>@include('admin.surveys-table',['surveys'=>$surveyOverview])</section>
</div>
</div>
@include('admin.survey-create')

@foreach($categories as $key=>$meta)
    <dialog class="form-dialog" id="mel-add-{{ $key }}" aria-labelledby="mel-add-{{ $key }}-title">
        <div class="dialog-heading"><h2 id="mel-add-{{ $key }}-title">Add {{ strtolower($meta[0]) }} record</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
        <form class="dialog-form" method="post" action="/admin/mel/{{ $key }}">
            @csrf
            @foreach(array_slice($meta,1) as [$field,$label,$type])
                <div class="field">
                    <label for="mel-{{ $key }}-{{ $field }}">{{ $label }}</label>
                    <input id="mel-{{ $key }}-{{ $field }}" name="{{ $field }}" type="{{ $type==='number'?'number':($type==='date'?'date':'text') }}" @if($type==='number') step="any" min="0" @endif maxlength="5000">
                </div>
            @endforeach
            <div class="dialog-actions"><button type="submit">Save record</button></div>
        </form>
    </dialog>
@endforeach
@endsection
