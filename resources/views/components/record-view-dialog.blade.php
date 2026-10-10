{{--
    Read-only record details in a native <dialog>.
    Props:
      $id      string  Unique dialog id (opened via data-dialog-open="{{ $id }}").
      $title   string  Heading text; also the dialog's accessible name.
      $fields  array   List of ['label' => string, 'value' => mixed].
                       Null or empty-string values render as an em dash.
                       Arrays and objects render as readable text.
--}}
@props([
    'id',
    'title',
    'fields' => [],
])

@php
    // Split fields: ungrouped (curated) first, then grouped by 'group' name in first-appearance order.
    $ungrouped = [];
    $groups = [];
    foreach ($fields as $field) {
        $field = \App\Services\RecordPresentation::field($field);
        $group = $field['group'] ?? null;
        if ($group === null || $group === '') {
            $ungrouped[] = $field;
        } else {
            $groups[(string) $group][] = $field;
        }
    }

    // Shared row formatting: bools to Yes/No, arrays/objects to JSON, empty to em dash.
    $formatValue = function ($field) {
        $value = $field['value'] ?? null;
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $isEmpty = $value === null || $value === '';
        return $isEmpty ? '—' : $value;
    };
@endphp

<dialog class="form-dialog record-view-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="dialog-heading">
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="secondary small" data-dialog-close aria-label="Close">Close</button>
    </div>
    <div class="dialog-form">
        <dl class="record-view-list">
            @foreach ($ungrouped as $field)
                <div class="record-view-row">
                    <dt>{{ $field['label'] }}</dt>
                    <dd>{{ $formatValue($field) }}</dd>
                </div>
            @endforeach
        </dl>
        {{-- A <dl> may only contain div/dt/dd, so each group gets its own heading + <dl>. --}}
        @foreach ($groups as $groupName => $groupFields)
            <h3 class="record-view-heading">{{ $groupName }}</h3>
            <dl class="record-view-list">
                @foreach ($groupFields as $field)
                    <div class="record-view-row">
                        <dt>{{ $field['label'] }}</dt>
                        <dd>{{ $formatValue($field) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endforeach
    </div>
</dialog>
