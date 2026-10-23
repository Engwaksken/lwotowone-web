@php
    $customLogo=$siteSettings['site_logo']??null;
    $logoPath=$customLogo?:collect(['assets/logo.svg','assets/logo.png','assets/logo.webp','assets/logo.jpg'])
        ->first(fn($path)=>file_exists(public_path($path)));
    $logoClass=$logoClass??'brand-logo-image';
    $logoSrc=$logoPath?(preg_match('/^https?:\/\//i',$logoPath)?$logoPath:'/'.ltrim(preg_replace('#^/?public/#','',$logoPath),'/')):null;
@endphp
@if($logoPath)
    <img class="{{ $logoClass }}" src="{{ $logoSrc }}" alt="Lwotowone Enterprises Ltd">
@else
    <span class="brand-wordmark">LWOTOWONE<small>ENTERPRISES LTD</small></span>
@endif
