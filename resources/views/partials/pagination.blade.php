@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);
    @endphp

    <div class="ui-table-footer">
        <div class="ui-pagination-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }}
            of {{ $paginator->total() }} records
        </div>

        <nav class="ui-pagination" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="ui-pagination-disabled" aria-disabled="true">
                    Previous
                </span>
            @else
                <a
                    class="ui-pagination-link"
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                >
                    Previous
                </a>
            @endif

            @if ($startPage > 1)
                <a
                    class="ui-pagination-link"
                    href="{{ $paginator->url(1) }}"
                >
                    1
                </a>

                @if ($startPage > 2)
                    <span class="ui-pagination-disabled">...</span>
                @endif
            @endif

            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span
                        class="ui-pagination-current"
                        aria-current="page"
                    >
                        {{ $page }}
                    </span>
                @else
                    <a
                        class="ui-pagination-link"
                        href="{{ $paginator->url($page) }}"
                    >
                        {{ $page }}
                    </a>
                @endif
            @endfor

            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <span class="ui-pagination-disabled">...</span>
                @endif

                <a
                    class="ui-pagination-link"
                    href="{{ $paginator->url($lastPage) }}"
                >
                    {{ $lastPage }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a
                    class="ui-pagination-link"
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                >
                    Next
                </a>
            @else
                <span class="ui-pagination-disabled" aria-disabled="true">
                    Next
                </span>
            @endif
        </nav>
    </div>
@endif
