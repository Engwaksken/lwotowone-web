@php
    $family=$siteSettings['font_family']??'Arial';
    if(!preg_match('/^[\pL\pN _-]{1,120}$/u',$family))$family='Arial';
    $fontCss=$family==='system'?'system-ui, sans-serif':json_encode($family,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).', Arial, sans-serif';
    $fontUrl=$siteSettings['font_url']??'';
@endphp
<style>
    :root{--site-font-family:{!! $fontCss !!}}
    @if($fontUrl&&filter_var($fontUrl,FILTER_VALIDATE_URL)&&str_starts_with($fontUrl,'https://'))
    @font-face{font-family:{!! json_encode($family,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};src:url({!! json_encode($fontUrl,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!});font-display:swap}
    @endif
</style>
