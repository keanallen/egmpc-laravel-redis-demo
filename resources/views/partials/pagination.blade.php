<nav class="pagination" aria-label="Pagination">
    <span>{{ number_format($employees->total()) }} employees total · Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}</span>
    <div class="page-actions">
        @if($employees->previousPageUrl())<a class="button secondary" href="{{ $employees->previousPageUrl() }}" rel="prev">Previous</a>@endif
        @if($employees->nextPageUrl())<a class="button secondary" href="{{ $employees->nextPageUrl() }}" rel="next">Next</a>@endif
    </div>
</nav>
