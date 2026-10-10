@props(['url','type'=>'document','title'=>'Learning content','external'=>false])
@php
    $embed=null;
    if($type==='video'){
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));$path=(string)parse_url($url,PHP_URL_PATH);$videoId=null;
        if($host==='youtu.be')$videoId=trim($path,'/');
        elseif($host==='youtube.com'||str_ends_with($host,'.youtube.com')){parse_str((string)parse_url($url,PHP_URL_QUERY),$query);$videoId=$query['v']??(preg_match('~/(?:embed|shorts)/([A-Za-z0-9_-]{6,})~',$path,$match)?$match[1]:null);}
        if($videoId&&preg_match('/^[A-Za-z0-9_-]{6,20}$/',$videoId))$embed='https://www.youtube-nocookie.com/embed/'.$videoId.'?rel=0';
        elseif(($host==='vimeo.com'||str_ends_with($host,'.vimeo.com'))&&preg_match('~/(?:video/)?([0-9]{5,})~',$path,$match))$embed='https://player.vimeo.com/video/'.$match[1];
    }
@endphp
@if($type==='video')
    @if($embed)<div class="lesson-video"><iframe src="{{ $embed }}" title="{{ $title }} video" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    @else<video class="resource-video" controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline preload="metadata" aria-label="{{ $title }}"><source src="{{ $url }}">Your browser cannot play this video.</video>@endif
@elseif($type==='audio')
    <audio class="resource-audio" controls preload="metadata" aria-label="{{ $title }}"><source src="{{ $url }}">Your browser cannot play this audio.</audio>
@elseif($type==='image')
    <img class="resource-image-viewer" src="{{ $url }}" alt="{{ $title }}" loading="lazy">
@elseif(in_array($type,['pdf','text']))
    <div class="resource-document-viewer"><iframe src="{{ $url }}{{ $type==='pdf'?'#toolbar=0&navpanes=0':'' }}" title="{{ $title }} document" loading="lazy" @if($external)sandbox referrerpolicy="no-referrer"@endif></iframe></div>
@endif
@if($external||$type==='document')<p><a class="button secondary small" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Open {{ $type==='document'?'document':'content' }}</a></p>@endif
