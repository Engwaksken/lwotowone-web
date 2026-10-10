{{--
    Standard full-width GET filter row (search, optional status, optional period, Apply, Reset).
    Props:
      $action             string  Form action and Reset link target.
       $showSearch         bool    Renders the search field when true (default). When false, it is omitted entirely.
       $searchName         string  GET parameter for search.
      $search             string  Current search value.
      $searchLabel        string  Visible label for search.
      $searchPlaceholder  string  Placeholder for search.
      $statusName         string  GET parameter for status.
      $statusOptions      array   value => label. Status select is hidden when empty.
      $status             string  Current status value.
      $statusLabel        string  Visible label for status.
      $periodName         string  GET parameter for period.
      $periodOptions      array   value => label. Period select is hidden when empty.
      $period             string  Current period value.
      $periodLabel        string  Visible label for period.
      $idPrefix           string  Prefix for control ids; set uniquely when several bars share a page.
    Slot: optional extra fields, rendered before the actions.
--}}
@props([
    'action',
    'showSearch' => true,
    'searchName' => 'search',
    'search' => '',
    'searchLabel' => 'Search',
    'searchPlaceholder' => 'Search',
    'statusName' => 'status',
    'statusOptions' => [],
    'status' => '',
    'statusLabel' => 'Status',
    'periodName' => 'period',
    'periodOptions' => [],
    'period' => '',
    'periodLabel' => 'Period',
    'idPrefix' => 'filter',
])

<form method="GET" action="{{ $action }}" class="filter-bar" role="search">
    @if ($showSearch)
        <div class="field search-field filter-bar__field">
            <label for="{{ $idPrefix }}-search">{{ $searchLabel }}</label>
            <input id="{{ $idPrefix }}-search" type="search" name="{{ $searchName }}" value="{{ $search }}" placeholder="{{ $searchPlaceholder }}" maxlength="255">
        </div>
    @endif

    @if (! empty($statusOptions))
        <div class="field filter-bar__field">
            <label for="{{ $idPrefix }}-status">{{ $statusLabel }}</label>
            <select id="{{ $idPrefix }}-status" name="{{ $statusName }}">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $status === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (! empty($periodOptions))
        <div class="field filter-bar__field">
            <label for="{{ $idPrefix }}-period">{{ $periodLabel }}</label>
            <select id="{{ $idPrefix }}-period" name="{{ $periodName }}">
                @foreach ($periodOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $period === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{ $slot }}

    <div class="filter-actions filter-bar__actions">
        <button type="submit">Apply</button>
        <a href="{{ $action }}">Reset</a>
    </div>
</form>
