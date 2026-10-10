@extends('layout')
@section('content')
<div class="page-wrap">
    @php
        $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
        $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
        $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
            ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
            ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
            ->values()->all();
    @endphp
    <a href="/admin/certificates">Back to certificates</a>
    <span class="eyebrow">{{ $course->title }}</span>
    <h1 class="page-title">Certificate design</h1>
    <p class="page-intro">Upload a one-page PDF, PNG or JPG. Positions are percentages from the top left. Drag an enabled field in the preview or edit its numbers. Save, then check the sample PDF.</p>
    <form class="panel" method="post" action="/admin/certificates/{{ $course->id }}/template" enctype="multipart/form-data" data-certificate-editor>@csrf @method('PUT')
        <div class="field"><label for="certificate-file">Certificate design (up to 10 MB)</label><input id="certificate-file" type="file" name="template" accept=".pdf,.png,.jpg,.jpeg" @required(!$template)></div>
        @if($template)
            <div class="certificate-canvas" data-certificate-canvas style="aspect-ratio:{{ $template->width_mm }}/{{ $template->height_mm }}" data-width-mm="{{ $template->width_mm }}" data-background="/admin/certificates/{{ $course->id }}/background" data-format="{{ $template->format }}">
                @if($template->format==='pdf')<canvas data-certificate-pdf></canvas>@else<img src="/admin/certificates/{{ $course->id }}/background" alt="Certificate design">@endif
                @foreach($placements as $key=>$place)<span class="certificate-placement" data-placement="{{ $key }}">{{ $fields[$key] }}</span>@endforeach
            </div>
            <p data-certificate-preview-status role="status"></p>
        @endif
        <div class="table-wrap"><table>
            <thead><tr><th>Field</th><th>Include</th><th>X %</th><th>Y %</th><th>Width %</th><th>Font size</th><th>Alignment</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($placements as $key=>$place)
                <tr data-placement-controls="{{ $key }}"><td>{{ $fields[$key] }}</td><td><input type="checkbox" name="placements[{{ $key }}][enabled]" value="1" @checked($place['enabled']) aria-label="Include {{ $fields[$key] }}"></td>@foreach(['x','y','width','font_size'] as $property)<td><input type="number" name="placements[{{ $key }}][{{ $property }}]" value="{{ $place[$property] }}" min="{{ $property==='font_size'?8:($property==='width'?5:0) }}" max="{{ $property==='font_size'?48:($property==='width'?100:95) }}" step="0.1" aria-label="{{ $fields[$key] }} {{ $property }}" required></td>@endforeach<td><select name="placements[{{ $key }}][align]" aria-label="{{ $fields[$key] }} alignment">@foreach(['L'=>'Left','C'=>'Center','R'=>'Right'] as $value=>$label)<option value="{{ $value }}" @selected($place['align']===$value)>{{ $label }}</option>@endforeach</select></td><td><x-record-view-trigger :dialogId="'view-placement-'.$key" :name="$fields[$key]" /><x-record-view-dialog :id="'view-placement-'.$key" :title="$fields[$key]" :fields="array_merge([['label'=>'Field','value'=>$fields[$key]],['label'=>'Included','value'=>(bool) $place['enabled']],['label'=>'X %','value'=>$place['x']],['label'=>'Y %','value'=>$place['y']],['label'=>'Width %','value'=>$place['width']],['label'=>'Font size','value'=>$place['font_size']],['label'=>'Alignment','value'=>['L'=>'Left','C'=>'Center','R'=>'Right'][$place['align']]??$place['align']]], [['label'=>'Field key','value'=>$key,'group'=>'More details']], $moreDetails($place, ['enabled','x','y','width','font_size','align']))" /></td></tr>
            @endforeach
            </tbody>
        </table></div>
        <button type="submit">Save design</button>
    </form>
    @if($template)<a class="button secondary" href="/admin/certificates/{{ $course->id }}/preview.pdf" target="_blank" rel="noopener">Preview sample PDF</a>@endif
</div>
@endsection
