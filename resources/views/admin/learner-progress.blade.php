@extends('layout')
@section('title','Learner progress')
@section('content')
<div class="page-wrap">
@php
    $languageName = fn ($code) => $code === 'lg' ? 'Luganda' : 'English';
    $statusLink = fn ($status) => '/admin/learner-progress?'.http_build_query(array_filter(['q'=>$filters['q'],'course_id'=>$filters['course_id'],'language'=>$filters['language']!=='all'?$filters['language']:null,'status'=>$status]));
    $cards = [
        ['all','Enrollments',$stats['total'],'fa-users'],
        ['not_started','Not started',$stats['not_started'],'fa-hourglass-start'],
        ['in_progress','In progress',$stats['in_progress'],'fa-spinner'],
        ['completed','All lessons completed',$stats['completed'],'fa-check-circle'],
        ['no_lessons','No lessons in their language',$stats['no_lessons'],'fa-language'],
    ];
@endphp
<span class="eyebrow">Learning and content</span>
<h1 class="page-title">Learner progress</h1>
<p class="page-intro">Lessons completed in each enrolled course, counted in the learner’s own learning language. Select a card to show only those learners.</p>

<section class="stats progress-stats" aria-label="Learner progress summary">
    @foreach($cards as [$status,$label,$value,$icon])
        <a class="stat" href="{{ $statusLink($status==='all'?null:$status) }}" @if($filters['status']===$status) aria-current="true" @endif><span class="stat-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></span><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></a>
    @endforeach
    <div class="stat"><span class="stat-icon"><i class="fas fa-percentage" aria-hidden="true"></i></span><strong>{{ $stats['average'] }}%</strong><span>Average lesson completion</span></div>
</section>

<x-filter-bar
    :action="'/admin/learner-progress'"
    searchName="q"
    :search="$filters['q']"
    searchLabel="Search learners"
    searchPlaceholder="Name, email or learner number"
    :statusOptions="['all'=>'All lesson progress']+$statuses"
    :status="$filters['status']"
    statusLabel="Lesson progress"
    idPrefix="progress"
>
    <div class="field filter-bar__field">
        <label for="progress-course">Course</label>
        <select id="progress-course" name="course_id">
            <option value="">All courses</option>
            @foreach($courses as $course)<option value="{{ $course->id }}" @selected((string)$filters['course_id']===(string)$course->id)>{{ $course->title }}</option>@endforeach
        </select>
    </div>
    <div class="field filter-bar__field">
        <label for="progress-language">Language</label>
        <select id="progress-language" name="language">
            @foreach(['all'=>'All languages','en'=>'English','lg'=>'Luganda'] as $value=>$label)<option value="{{ $value }}" @selected($filters['language']===$value)>{{ $label }}</option>@endforeach
        </select>
    </div>
</x-filter-bar>

<section class="panel enrollment-panel">
    <div class="mel-panel-heading"><div><h2>Enrolled learners</h2><p>Open a row’s lesson list to see each lesson, or change the course a learner is enrolled in.</p></div><span class="tag">{{ number_format($rows->total()) }} {{ \Illuminate\Support\Str::plural('enrollment',$rows->total()) }}</span></div>
    <div class="table-wrap"><table><thead><tr><th>Learner</th><th>Course</th><th>Language</th><th>Lessons</th><th>Assessments</th><th>Last lesson completed</th><th>Actions</th></tr></thead><tbody>
    @forelse($rows as $row)
        @php $key=$row->user_id.'-'.$row->course_id; $p=$progress[$key]; $taken=$learnerCourses->get($row->user_id,collect()); @endphp
        <tr>
            <td>{{ $row->name }}<small>{{ $row->email }}@if($row->learner_no) · {{ $row->learner_no }}@endif</small></td>
            <td>{{ $row->title }}<small>{{ $row->payment_confirmed_at?'Enrolled':'Trial' }}</small></td>
            <td>{{ $languageName($p['language']) }}</td>
            <td>@if($p['lessons_total'])<progress max="100" value="{{ $p['percent'] }}" aria-label="Lesson completion for {{ $row->name }} in {{ $row->title }}">{{ $p['percent'] }}%</progress><small>{{ $p['lessons_completed'] }} of {{ $p['lessons_total'] }} · {{ $p['percent'] }}%</small>@else<small>No lessons published in {{ $languageName($p['language']) }}</small>@endif</td>
            <td>@if($p['awaiting_assessments'])<small>No assignments in {{ $languageName($p['language']) }} yet</small>@else{{ $p['assignments_passed'] }} of {{ $p['assignments_total'] }} passed @endif</td>
            <td>{{ $p['last_completed_at']?\Illuminate\Support\Carbon::parse($p['last_completed_at'])->format('d M Y'):'—' }}</td>
            <td><div class="actions">
                <button type="button" class="secondary small icon-action" data-dialog-open="progress-{{ $key }}" title="View lesson progress" aria-label="View lesson progress for {{ $row->name }}"><i class="fas fa-tasks" aria-hidden="true"></i></button>
                <button type="button" class="secondary small icon-action" data-dialog-open="change-course-{{ $key }}" title="Change course" aria-label="Change course for {{ $row->name }}"><i class="fas fa-exchange-alt" aria-hidden="true"></i></button></div>
                <dialog class="form-dialog" id="progress-{{ $key }}"><div class="dialog-heading"><h2>{{ $row->name }} · {{ $row->title }}</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div><p>{{ $p['lessons_completed'] }} of {{ $p['lessons_total'] }} {{ $languageName($p['language']) }} lessons completed.</p><div class="table-wrap"><table><thead><tr><th>#</th><th>Lesson</th><th>Status</th></tr></thead><tbody>@forelse($p['lessons'] as $i=>$lesson)<tr><td>{{ $i+1 }}</td><td>{{ $lesson['title'] }}@if($lesson['module'])<small>{{ $lesson['module'] }}</small>@endif</td><td>{{ $lesson['completed_at']?'Completed '.\Illuminate\Support\Carbon::parse($lesson['completed_at'])->format('d M Y'):'Not started' }}</td></tr>@empty<tr><td colspan="3">No lessons published in this learner’s language.</td></tr>@endforelse</tbody></table></div></dialog>
                <dialog class="form-dialog" id="change-course-{{ $key }}"><div class="dialog-heading"><h2>Change {{ $row->name }}’s course</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div><form class="dialog-form" method="post" action="/admin/enrollment/learners/{{ $row->user_id }}/courses/{{ $row->course_id }}" data-confirm="Move this learner to the selected course?">@csrf @method('PUT')<p>Currently enrolled in <strong>{{ $row->title }}</strong>. Their payment status moves with them. Lesson progress in {{ $row->title }} stays on record but does not count toward the new course.</p><div class="field"><label for="change-course-select-{{ $key }}">New course</label><select id="change-course-select-{{ $key }}" name="course_id" required><option value="">Choose course</option>@foreach($courses->where('id','!=',$row->course_id) as $course)<option value="{{ $course->id }}" @disabled($taken->contains($course->id))>{{ $course->title }}</option>@endforeach</select></div><div class="dialog-actions"><button>Change course</button></div></form></dialog>
            </td>
        </tr>
    @empty
        <tr><td colspan="7">No enrolled learners match these filters.</td></tr>
    @endforelse
    </tbody></table></div>
    @include('partials.pagination',['rows'=>$rows])
</section>
</div>
@endsection
