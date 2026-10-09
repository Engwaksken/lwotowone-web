<nav class="pagination" aria-label="Pagination">
    @if($rows->previousPageUrl())
        <a href="{{ $rows->previousPageUrl() }}" aria-label="Go to previous page">Previous</a>
    @endif
    @if(!($hidePageCount ?? false))
        <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
    @endif
    @if($rows->nextPageUrl())
        <a href="{{ $rows->nextPageUrl() }}" aria-label="Go to next page">Next</a>
    @endif
</nav>
