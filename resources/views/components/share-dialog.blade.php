@props(['id','title','url','qrUrl'])
<dialog class="form-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="dialog-heading"><h2 id="{{ $id }}-title">Share {{ $title }}</h2><button type="button" class="secondary small" data-dialog-close>Close</button></div>
    <div class="dialog-form share-dialog-body">
        <img src="{{ $qrUrl }}" alt="QR code for {{ $title }}" width="280" height="280" loading="lazy">
        <label for="{{ $id }}-link">Participant link</label><input id="{{ $id }}-link" type="url" value="{{ $url }}" readonly>
        <div class="actions"><button type="button" class="secondary small" data-copy-link="{{ $id }}-link">Copy link</button><a class="button secondary small" href="{{ $qrUrl }}?download=1">Download QR</a><a class="button secondary small" href="{{ $url }}" target="_blank" rel="noopener">Open form</a></div>
        <p class="muted" data-copy-status role="status"></p>
    </div>
</dialog>
