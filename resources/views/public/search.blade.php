@extends('layout')
@section('title','Search results | Lwotowone')
@section('content')
<span class="eyebrow">Search Lwotowone</span>
<h1>Results for “{{ $query }}”</h1>
<p>{{ $results->count() }} result{{ $results->count()===1?'':'s' }} found across our public content.</p>
<div class="grid search-results">
@forelse($results as $result)
    <article class="card"><span class="tag">{{ $result['type'] }}</span><h2>{{ $result['title'] }}</h2><p>{{ \Illuminate\Support\Str::limit(strip_tags($result['summary']),220) }}</p><a href="{{ $result['url'] }}">View details</a></article>
@empty
    <div class="empty">No results found. Try another search term.</div>
@endforelse
</div>
@endsection
