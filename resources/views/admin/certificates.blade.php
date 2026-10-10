@extends('layout')
@section('content')
<div class="page-wrap">
    <span class="eyebrow">Learning and participation</span>
    <h1 class="page-title">Certificates</h1>
    <p class="page-intro">Manage certificate designs for courses and events. Add a certificate, choose its course or event, then position the fields on the uploaded template.</p>
    @if(auth()->user()->role==='admin')
        <div class="toolbar"><button type="button" data-dialog-open="add-certificate"><i class="fas fa-plus" aria-hidden="true"></i> Add certificate</button></div>
    @endif
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Certificate management">
            <button type="button" role="tab" id="tab-certificate-templates" aria-controls="panel-certificate-templates" aria-selected="true">Certificate templates <span class="tab-count">{{ $templates->total() }}</span></button>
            <button type="button" role="tab" id="tab-certificate-recommendations" aria-controls="panel-certificate-recommendations" aria-selected="false" tabindex="-1">Course recommendations <span class="tab-count">{{ $learners->count() }}</span></button>
        </div>
        <section class="tab-panel" role="tabpanel" id="panel-certificate-templates" aria-labelledby="tab-certificate-templates">
            <div class="panel table-wrap cms-table-wrap"><table class="data-table cms-table certificate-table">
                <caption class="sr-only">Course and event certificate templates</caption>
                <thead><tr><th scope="col" class="column-primary">Certificate name</th><th scope="col" class="column-status">For</th><th scope="col" class="column-context">Course / Event Name</th><th scope="col" class="column-status">Format</th><th scope="col" class="column-date">Updated</th><th scope="col" class="column-actions">Actions</th></tr></thead>
                <tbody>
                @forelse($templates as $template)
                    @php
                        $type=$template->event_id?'event':'course';$subjectName=$template->event_name??$template->course_name;
                        $designUrl='/admin/certificates/'.($type==='event'?'events/'.$template->event_id:$template->course_id);
                    @endphp
                    <tr>
                        <td class="column-primary"><strong>{{ $template->name??$subjectName.' certificate' }}</strong><small>Template #{{ $template->id }}</small></td>
                        <td class="column-status"><span class="certificate-type-badge" data-type="{{ $type }}"><i class="fas {{ $type==='event'?'fa-calendar-alt':'fa-book-open' }}" aria-hidden="true"></i> {{ ucfirst($type) }}</span></td>
                        <td class="column-context">{{ $subjectName }}</td>
                        <td class="column-status">{{ strtoupper($template->format) }}</td>
                        <td class="column-date">{{ \Carbon\Carbon::parse($template->updated_at)->format('d M Y') }}</td>
                        <td class="column-actions"><div class="actions">
                            <x-record-view-trigger :dialogId="'view-template-'.$template->id" :name="$template->name??$subjectName" />
                            @if(auth()->user()->role==='admin')
                                <a class="secondary small icon-action button" href="{{ $designUrl }}/template" aria-label="Edit {{ $template->name??$subjectName }} design" title="Edit design"><i class="fas fa-pen" aria-hidden="true"></i></a>
                                <a class="secondary small icon-action button" href="{{ $designUrl }}/preview.pdf" target="_blank" rel="noopener" aria-label="Preview {{ $template->name??$subjectName }} PDF" title="Preview PDF"><i class="fas fa-file-pdf" aria-hidden="true"></i></a>
                            @endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="6" class="table-empty">No certificate templates yet. @if(auth()->user()->role==='admin')Use “Add certificate” to create a course or event design.@endif</td></tr>@endforelse
                </tbody>
            </table>@include('partials.pagination',['rows'=>$templates])</div>
        </section>
        <section class="tab-panel" role="tabpanel" id="panel-certificate-recommendations" aria-labelledby="tab-certificate-recommendations" hidden>
            <p class="muted">Assigned instructors recommend course certificates after all published lessons and practical assessments are complete.</p>
            @php $courseMap=$courses->keyBy('id'); @endphp
            <div class="panel table-wrap cms-table-wrap"><table class="data-table cms-table">
                <caption class="sr-only">Course certificate recommendations</caption>
                <thead><tr><th scope="col" class="column-primary">Learner</th><th scope="col" class="column-context">Course Name</th><th scope="col" class="column-status">Completion</th><th scope="col" class="column-context">Recommendation</th><th scope="col" class="column-actions">Actions</th></tr></thead>
                <tbody>
                @forelse($learners as $learner)
                    @php
                        $course=$courseMap->get($learner->course_id);
                        $ready=\App\Services\LearningAccess::completionReady(\App\Models\User::find($learner->id),$course->id);
                        $certificate=$certificates->where('course_id',$course->id)->firstWhere('user_id',$learner->id);
                    @endphp
                    <tr>
                        <td class="column-primary"><strong>{{ $learner->name }}</strong></td>
                        <td class="column-context">{{ $course->title }}</td>
                        <td class="column-status"><span class="status-badge" data-status="{{ $ready?'active':'pending' }}">{{ $ready?'Complete':'In progress' }}</span></td>
                        <td class="column-context">{{ $certificate?'Recommended':'Awaiting recommendation' }}@if($certificate)<small>{{ $certificate->reference }}</small>@endif</td>
                        <td class="column-actions"><div class="actions">
                            <x-record-view-trigger :dialogId="'view-recommendation-'.$course->id.'-'.$learner->id" :name="$learner->name" />
                            @if(!$certificate&&auth()->user()->role==='instructor'&&$ready)<form method="post" action="/admin/certificates/{{ $course->id }}/recommend">@csrf<input type="hidden" name="user_id" value="{{ $learner->id }}"><button type="submit" class="small">Recommend</button></form>@endif
                        </div>
                        <x-record-view-dialog :id="'view-recommendation-'.$course->id.'-'.$learner->id" :title="$learner->name" :fields="[['label'=>'Learner','value'=>$learner->name],['label'=>'Course Name','value'=>$course->title],['label'=>'Completion','value'=>$ready?'Complete':'In progress'],['label'=>'Recommendation','value'=>$certificate?'Recommended':'Awaiting recommendation'],['label'=>'Certificate reference','value'=>$certificate?->reference]]" />
                        </td>
                    </tr>
                @empty<tr><td colspan="5" class="table-empty">No course learners to review yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    @foreach($templates as $template)
        <x-record-view-dialog :id="'view-template-'.$template->id" :title="$template->name??($template->event_name??$template->course_name).' certificate'" :fields="[['label'=>'Certificate name','value'=>$template->name],['label'=>'For','value'=>$template->event_id?'Event':'Course'],['label'=>$template->event_id?'Event Name':'Course Name','value'=>$template->event_name??$template->course_name],['label'=>'Format','value'=>strtoupper($template->format)],['label'=>'Width (mm)','value'=>$template->width_mm],['label'=>'Height (mm)','value'=>$template->height_mm],['label'=>'Updated','value'=>\Carbon\Carbon::parse($template->updated_at)->format('d M Y, H:i')]]" />
    @endforeach

    @if(auth()->user()->role==='admin')
        @php $selectedType=old('subject_type','course'); @endphp
        <dialog class="form-dialog" id="add-certificate" aria-labelledby="add-certificate-title">
            <div class="dialog-heading"><h2 id="add-certificate-title">Add certificate</h2><button type="button" class="secondary small" data-dialog-close>Close</button></div>
            <form class="dialog-form" method="post" action="/admin/certificates" enctype="multipart/form-data" data-certificate-create>@csrf<input type="hidden" name="_modal_id" value="add-certificate">
                <div class="field"><label for="new-certificate-name">Certificate name</label><input id="new-certificate-name" type="text" name="name" maxlength="255" value="{{ old('name') }}" placeholder="e.g. Enterprise course completion" required></div>
                <div class="field"><label for="new-certificate-type">Certificate for</label><select id="new-certificate-type" name="subject_type" data-certificate-subject required><option value="course" @selected($selectedType==='course')>Course</option><option value="event" @selected($selectedType==='event')>Event</option></select></div>
                <div class="field" data-certificate-target="course" @if($selectedType!=='course')hidden @endif><label for="new-certificate-course">Course Name</label><select id="new-certificate-course" name="course_id" @required($selectedType==='course') @disabled($selectedType!=='course')><option value="">Choose a course</option>@foreach($courses->sortBy('title') as $course)<option value="{{ $course->id }}" @selected((string)old('course_id')===(string)$course->id) @disabled($configuredCourses->contains($course->id))>{{ $course->title }}{{ $configuredCourses->contains($course->id)?' — template already added':'' }}</option>@endforeach</select></div>
                <div class="field" data-certificate-target="event" @if($selectedType!=='event')hidden @endif><label for="new-certificate-event">Event Name</label><select id="new-certificate-event" name="event_id" @required($selectedType==='event') @disabled($selectedType!=='event')><option value="">Choose an event</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected((string)old('event_id')===(string)$event->id) @disabled($configuredEvents->contains($event->id))>{{ $event->title }}{{ $configuredEvents->contains($event->id)?' — template already added':'' }}</option>@endforeach</select></div>
                <div class="field"><label for="new-certificate-background">Upload certificate template</label><input id="new-certificate-background" type="file" name="template" accept=".pdf,.png,.jpg,.jpeg" required><small>Single-page PDF, PNG or JPG. Maximum 10 MB. You can drag fields and customise their text on the next screen.</small></div>
                <p class="muted">One template per course or event. Update an existing template using its edit action in the table.</p>
                <div class="dialog-actions"><button type="submit">Add and customise</button></div>
            </form>
        </dialog>
        @if($errors->any()&&old('_modal_id')==='add-certificate')<div data-reopen-dialog="add-certificate" hidden></div>@endif
    @endif
</div>
@endsection
