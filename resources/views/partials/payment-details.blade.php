@php $configuration=json_decode($gateway->configuration??'{}',true)??[]; @endphp
@foreach(config('payments.types.'.$gateway->provider_type,[]) as $key=>$field)
    @if(!($field['private']??false)&&!empty($configuration[$key]))
        <p><strong>{{ $field['label'] }}:</strong> @if(($field['type']??'')==='url')<a href="{{ $configuration[$key] }}" target="_blank" rel="noopener noreferrer">Open payment link</a>@else{{ $configuration[$key] }}@endif</p>
    @endif
@endforeach
