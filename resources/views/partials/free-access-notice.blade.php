@if(\App\Services\FreeAccess::active())
    @php $freeEnds=\App\Services\FreeAccess::endsAt(); @endphp
    <div class="notice success" role="status"><strong>Free access:</strong> all courses, learning materials and events are free @if($freeEnds)until <strong>{{ $freeEnds->format('d M Y, H:i') }}</strong>. After that, you can subscribe or pay to keep learning.@else for now. No payment is needed.@endif</div>
@endif
