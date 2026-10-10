@php
    $family=$siteSettings['font_family']??'Arial';
    if(!preg_match('/^[\pL\pN _-]{1,120}$/u',$family))$family='Arial';
    // CSS string literal. Only characters that could break out of the string or the <style> element
    // are written as CSS hex escapes (JSON \uXXXX escapes are not valid in CSS).
    $cssString=fn(string $value)=>'"'.preg_replace_callback('/[\\\\"<>\x00-\x1f]/',fn($m)=>'\\'.dechex(ord($m[0])).' ',$value).'"';
    $fontCss=$family==='system'?'system-ui, sans-serif':$cssString($family).', Arial, sans-serif';
    $fontUrl=$siteSettings['font_url']??'';
@endphp
<style>
    :root{--site-font-family:{!! $fontCss !!}}
    @if($fontUrl&&filter_var($fontUrl,FILTER_VALIDATE_URL)&&str_starts_with($fontUrl,'https://'))
    @font-face{font-family:{!! $cssString($family) !!};src:url({!! $cssString($fontUrl) !!});font-display:swap}
    @endif
</style>
