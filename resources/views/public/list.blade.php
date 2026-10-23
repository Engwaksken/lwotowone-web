@extends('layout')
@section('content')
<span class="eyebrow">Discover your next step</span>
<h1>{{ ['programs'=>'Programmes','posts'=>'Stories'][$type]??ucfirst($type) }}</h1>
<div class="grid public-list-grid">
    @forelse($rows as $row)
        <article class="card">
            <h3>{{ $row->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($row->summary??$row->description??$row->body??'',200) }}</p>
            @if(isset($row->location))<small>{{ $row->location }}</small>@endif
            <a href="/{{ $type }}/{{ $row->id }}">View details</a>
        </article>
    @empty
        <div class="empty">No published items available.</div>
    @endforelse
</div>
@include('partials.pagination',['rows'=>$rows,'hidePageCount'=>true])
@endsection
