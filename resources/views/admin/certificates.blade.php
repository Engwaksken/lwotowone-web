@extends('layout')
@section('content')
<div class="page-wrap">
    <h1 class="page-title">Certificates</h1>
    <p class="page-intro">Admins design certificates. Assigned instructors recommend learners once every published lesson and practical assessment is complete.</p>
    @foreach($courses as $course)
    <section class="panel">
        <h2>{{ $course->title }}</h2>
        @if(auth()->user()->role==='admin')<a class="button secondary" href="/admin/certificates/{{ $course->id }}/template">Edit design</a>@endif
        <div class="table-wrap"><table>
            <thead><tr><th>Learner</th><th>Completion</th><th>Recommendation</th></tr></thead>
            <tbody>
            @foreach($learners->where('course_id',$course->id) as $learner)
                @php $ready=\App\Services\LearningAccess::completionReady(\App\Models\User::find($learner->id),$course->id);$certificate=$certificates->where('course_id',$course->id)->firstWhere('user_id',$learner->id); @endphp
                <tr>
                    <td>{{ $learner->name }}</td>
                    <td>{{ $ready?'Complete':'In progress' }}</td>
                    <td>
                        @if($certificate)Recommended · {{ $certificate->reference }}
                        @elseif(auth()->user()->role==='instructor'&&$ready)
                            <form method="post" action="/admin/certificates/{{ $course->id }}/recommend">@csrf<input type="hidden" name="user_id" value="{{ $learner->id }}"><button type="submit">Recommend completion</button></form>
                        @else Waiting for completion or instructor recommendation.
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    @endforeach
</div>
@endsection
