@extends('layout')
@section('content')
<div class="page-wrap">
    <h1 class="page-title">Certificate of completion</h1>
    <p>{{ $user->name }} · {{ $course->title }}</p>
    <p>Reference {{ $certificate->reference }}</p>
    <a class="button" href="/certificates/{{ $course->id }}/file?download=1">Download certificate PDF</a>
    <div class="resource-document-viewer"><iframe src="/certificates/{{ $course->id }}/file" title="Your completion certificate"></iframe></div>
</div>
@endsection
