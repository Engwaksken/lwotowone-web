@extends('layout')
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
    <h1 class="page-title">Certificates</h1>
    <p class="page-intro">Admins design certificates. Assigned instructors recommend learners once every published lesson and practical assessment is complete.</p>
    @foreach($courses as $course)
    <section class="panel">
        <h2>{{ $course->title }}</h2>
        @if(auth()->user()->role==='admin')<a class="button secondary" href="/admin/certificates/{{ $course->id }}/template">Edit design</a>@endif
        <div class="table-wrap"><table>
            <thead><tr><th>Learner</th><th>Completion</th><th>Recommendation</th><th>Actions</th></tr></thead>
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
                    <td>
                        <x-record-view-trigger :dialogId="'view-certificate-'.$course->id.'-'.$learner->id" :name="$learner->name" />
                        <x-record-view-dialog :id="'view-certificate-'.$course->id.'-'.$learner->id" :title="$learner->name" :fields="array_merge([['label'=>'Learner','value'=>$learner->name],['label'=>'Course','value'=>$course->title],['label'=>'Completion','value'=>$ready?'Complete':'In progress'],['label'=>'Recommendation','value'=>$certificate?'Recommended':'Not recommended yet'],['label'=>'Certificate reference','value'=>$certificate?->reference]], $moreDetails($attributesOf($learner), ['name','course_id']), $certificate ? $moreDetails($attributesOf($certificate), ['user_id','course_id','reference','file_path']) : [])" />
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    @endforeach
</div>
@endsection
