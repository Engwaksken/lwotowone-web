@extends('layout')
@section('content')
<div class="page-wrap certificate-workspace">
    <a href="/admin/certificates">Back to certificates</a>
    <span class="eyebrow">{{ ucfirst($subjectType) }} · {{ $subject->title }}</span>
    <h1 class="page-title">Certificate design</h1>
    <p class="page-intro">Upload your design, then drag fields onto it. Click a field to change its colour, font and alignment. Arrow keys move a focused field; hold Shift for larger steps.</p>
    <form method="post" action="{{ $designUrl }}/template" enctype="multipart/form-data" data-certificate-editor>@csrf @method('PUT')
        <div class="panel certificate-upload-bar">
            <div class="field"><label for="certificate-name">Certificate name</label><input id="certificate-name" type="text" name="name" maxlength="255" value="{{ old('name',$template?->name??\Illuminate\Support\Str::limit($subject->title.' certificate',255,'')) }}" required></div>
            <div class="field"><label for="certificate-file">Certificate background</label><input id="certificate-file" type="file" name="template" accept=".pdf,.png,.jpg,.jpeg" @required(!$template)><small>Single-page PDF, PNG or JPG. Maximum 10 MB. New uploads preview immediately.</small></div>
            <button type="submit">Save design</button>
            @if($template)<a class="button secondary" href="{{ $designUrl }}/preview.pdf" target="_blank" rel="noopener">Preview saved PDF</a>@endif
        </div>
        <div class="certificate-editor-grid">
            <section class="panel certificate-preview-panel" aria-label="Design preview">
                <div class="certificate-field-palette"><strong>Add a field</strong><small>Drag a label onto the design, or click to enable it.</small>
                    <div class="certificate-field-buttons">@foreach($fields as $key=>$label)<button type="button" class="secondary small" draggable="true" data-add-placement="{{ $key }}">{{ $label }}</button>@endforeach</div>
                </div>
                <div class="certificate-canvas" data-certificate-canvas style="aspect-ratio:{{ $template?->width_mm??297 }}/{{ $template?->height_mm??210 }}" data-width-mm="{{ $template?->width_mm??297 }}" data-background="{{ $template?$designUrl.'/background':'' }}" data-format="{{ $template?->format??'' }}" @if(!$template)hidden @endif>
                    <canvas data-certificate-pdf @if(!$template||$template->format!=='pdf')hidden @endif></canvas>
                    <img data-certificate-image @if($template&&$template->format!=='pdf')src="{{ $designUrl }}/background"@else hidden @endif alt="Certificate design">
                    @foreach($placements as $key=>$place)<span class="certificate-placement" data-placement="{{ $key }}" tabindex="0" role="button" aria-label="Position {{ $fields[$key] }}">{{ $fields[$key] }}</span>@endforeach
                </div>
                <p data-certificate-preview-status role="status">{{ $template?'Drag a field to position it on the design.':'Upload a design to start positioning fields.' }}</p>
            </section>
            <section class="panel certificate-inspector" aria-label="Field properties">
                <h2>Field properties</h2><p class="muted">Select a field on the design or open its settings below.</p>
                @foreach($placements as $key=>$savedPlace)
                    @php $place=array_replace($savedPlace,(array)old('placements.'.$key,[])); @endphp
                    <details class="certificate-field-settings" data-placement-controls="{{ $key }}" @if($loop->first)open @endif>
                        <summary>{{ $fields[$key] }}</summary>
                        <div class="certificate-property-grid">
                            <label class="certificate-enabled"><input type="checkbox" name="placements[{{ $key }}][enabled]" value="1" @checked(old('placements')?old('placements.'.$key.'.enabled',false):$place['enabled'])> Include this field</label>
                            @foreach(['x'=>'Horizontal position (%)','y'=>'Vertical position (%)','width'=>'Text width (%)','font_size'=>'Font size (pt)'] as $property=>$label)
                                <div class="field"><label for="placement-{{ $key }}-{{ $property }}">{{ $label }}</label><input id="placement-{{ $key }}-{{ $property }}" type="number" name="placements[{{ $key }}][{{ $property }}]" value="{{ $place[$property] }}" min="{{ $property==='font_size'?8:($property==='width'?5:0) }}" max="{{ $property==='font_size'?48:($property==='width'?100:95) }}" step="0.1" required></div>
                            @endforeach
                            <div class="field"><label for="placement-{{ $key }}-color">Text colour</label><input id="placement-{{ $key }}-color" type="color" name="placements[{{ $key }}][color]" value="{{ $place['color'] }}"></div>
                            <div class="field"><label for="placement-{{ $key }}-align">Alignment</label><select id="placement-{{ $key }}-align" name="placements[{{ $key }}][align]">@foreach(['L'=>'Left','C'=>'Centre','R'=>'Right'] as $value=>$label)<option value="{{ $value }}" @selected($place['align']===$value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label for="placement-{{ $key }}-font">Font family</label><select id="placement-{{ $key }}-font" name="placements[{{ $key }}][font_family]">@foreach(\App\Services\CertificatePdf::FONTS as $value=>$label)<option value="{{ $value }}" @selected($place['font_family']===$value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label for="placement-{{ $key }}-style">Font style</label><select id="placement-{{ $key }}-style" name="placements[{{ $key }}][font_style]">@foreach(\App\Services\CertificatePdf::STYLES as $value=>$label)<option value="{{ $value }}" @selected($place['font_style']===$value)>{{ $label }}</option>@endforeach</select></div>
                        </div>
                    </details>
                @endforeach
            </section>
        </div>
    </form>
</div>
@endsection
