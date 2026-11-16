@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginação" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="btn btn-secondary" aria-disabled="true" style="opacity:.45">&larr; Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary">&larr; Anterior</a>
        @endif

        <p class="text-sm text-slate-600 sm:hidden">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</p>

        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="px-2 text-slate-400" aria-hidden="true">…</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="grid h-10 min-w-10 place-items-center rounded-md bg-slate-900 px-2 text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Ir para a página {{ $page }}" class="grid h-10 min-w-10 place-items-center rounded-md px-2 text-sm text-slate-700 hover:bg-slate-200">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary">Próxima &rarr;</a>
        @else
            <span class="btn btn-secondary" aria-disabled="true" style="opacity:.45">Próxima &rarr;</span>
        @endif
    </nav>
@endif
