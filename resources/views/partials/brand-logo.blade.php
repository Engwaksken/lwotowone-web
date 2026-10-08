@php
    $logoPath=collect(['assets/logo.svg','assets/logo.png','assets/logo.webp','assets/logo.jpg'])
        ->first(fn($path)=>file_exists(public_path($path)));
    $logoClass=$logoClass??'brand-logo-image';
@endphp
@if($logoPath)
    <img class="{{ $logoClass }}" src="{{ asset($logoPath) }}" alt="Lwotowone Enterprises Ltd">
@else
    <span class="brand-wordmark">LWOTOWONE<small>ENTERPRISES LTD</small></span>
@endif
