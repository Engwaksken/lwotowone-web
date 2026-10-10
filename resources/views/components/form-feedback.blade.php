@props(['modalId'=>null,'showSuccess'=>true])
@if($modalId===null||old('_modal_id')===$modalId)
    @if($showSuccess&&session('success'))<div class="notice success form-feedback" data-form-feedback role="status" tabindex="-1">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice error form-feedback" data-form-feedback role="alert" tabindex="-1"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@endif
