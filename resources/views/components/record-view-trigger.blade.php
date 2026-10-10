{{--
    Button that opens a <x-record-view-dialog> with the matching id.
    Props:
      $dialogId  string  Must match the dialog's :id.
      $name      string  Record name, announced to screen readers only.
    Accessible name: "View <name>".
--}}
@props([
    'dialogId',
    'name',
])

<button type="button" class="secondary small" data-dialog-open="{{ $dialogId }}">View<span class="sr-only"> {{ $name }}</span></button>
