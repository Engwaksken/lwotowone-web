@php
    $customLogo=$siteSettings['site_logo']??null;
    $logoPath=$customLogo&&file_exists(public_path(ltrim($customLogo,'/')))?$customLogo:collect(['assets/logo.svg','assets/logo.png','assets/logo.webp','assets/logo.jpg'])
        ->first(fn($path)=>file_exists(public_path($path)));
    $logoClass=$logoClass??'brand-logo-image';
@endphp
@if($logoPath)
    <img class="{{ $logoClass }}" src="{{ str_starts_with($logoPath,'/')?$logoPath:asset($logoPath) }}" alt="Lwotowone Enterprises Ltd">
@else
    <span class="brand-wordmark">LWOTOWONE<small>ENTERPRISES LTD</small></span>
@endif
