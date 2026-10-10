@extends('layout')
@section('content')
<div class="page-wrap"><a href="/admin/mel#mel-panel-surveys">Back to MEL</a><span class="eyebrow">Monitoring, evaluation and learning</span><h1 class="page-title">Surveys</h1><p class="page-intro">Build your own forms, publish a participant link or QR code, and track responses. Choose anonymous or named responses for each survey.</p><div class="toolbar"><button type="button" data-dialog-open="create-survey"><i class="fas fa-plus" aria-hidden="true"></i> Create survey</button></div>@include('admin.surveys-table')@include('partials.pagination',['rows'=>$surveys])@include('admin.survey-create')</div>
@endsection
