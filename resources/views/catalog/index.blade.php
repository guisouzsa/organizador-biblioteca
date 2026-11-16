@extends('layouts.app')

@section('title', 'Catálogo — '.config('app.name'))

@php
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    $secondary = collect($filters)->except('q')->filter()->count();
    $view = request('vista') === 'lista' ? 'lista' : 'grade';
    $q = fn (array $extra) => route('books.index', array_filter(array_merge(request()->query(), $extra)));
@endphp

@section('content')
    <x-shared.page-header eyebrow="Catálogo bibliográfico" title="Catálogo" :subtitle="$books->total().' '.($books->total() === 1 ? 'livro' : 'livros').($hasFilters ? ' encontrados' : ' cadastrados')">
        @can('catalog.create')
            <a href="{{ route('books.scanner') }}" class="btn btn-primary flex-1 sm:flex-none"><x-shared.icon name="scan" class="h-4 w-4" /> Escanear livro</a>
            <a href="{{ route('books.create') }}" class="btn btn-secondary flex-1 sm:flex-none">Cadastro manual</a>
        @endcan
    </x-shared.page-header>

    <form method="GET" action="{{ route('books.index') }}" role="search" aria-label="Pesquisar no catálogo" data-loading>
        <input type="hidden" name="vista" value="{{ $view }}">

        <div class="catalog-scope mb-5 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" aria-label="Grupo do acervo">
            <a href="{{ route('books.index', array_filter([...request()->except('page', 'collection_group')])) }}" @if(empty($filters['collection_group'])) aria-current="page" @endif class="catalog-scope__item {{ empty($filters['collection_group']) ? 'is-active' : '' }}">Todo o acervo</a>
            <a href="{{ route('books.index', [...request()->except('page'), 'collection_group' => 'school']) }}" @if(($filters['collection_group'] ?? null) === 'school') aria-current="page" @endif class="catalog-scope__item {{ ($filters['collection_group'] ?? null) === 'school' ? 'is-active' : '' }}"><x-shared.icon name="book" class="hidden h-4 w-4 sm:block" /> Escolar</a>
            <a href="{{ route('books.index', [...request()->except('page'), 'collection_group' => 'general']) }}" @if(($filters['collection_group'] ?? null) === 'general') aria-current="page" @endif class="catalog-scope__item {{ ($filters['collection_group'] ?? null) === 'general' ? 'is-active' : '' }}"><x-shared.icon name="grid" class="hidden h-4 w-4 sm:block" /> Geral</a>
        </div>

        <div>
            <label for="q" class="sr-only">Buscar por título, autor ou ISBN</label>
            <div class="relative">
                <x-shared.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Título, autor ou ISBN" autocomplete="off" enterkeyhint="search"
                       class="field !min-h-12 !pl-11 !pr-24 !text-base">
                <button type="submit" class="btn btn-primary absolute right-1 top-1/2 !min-h-10 -translate-y-1/2 !px-3.5">Buscar</button>
            </div>
        </div>

        <details data-open-lg class="group mt-3 lg:[&>summary]:hidden" @if ($secondary) open @endif>
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 text-sm font-medium text-slate-700 [&::-webkit-details-marker]:hidden">
                <x-shared.icon name="filter" class="h-4 w-4" /> Filtros
                @if ($secondary)<span class="rounded-sm bg-indigo-600 px-1.5 text-xs text-white">{{ $secondary }}</span>@endif
                <span class="ml-auto text-xs text-slate-500 group-open:hidden">mostrar</span>
            </summary>
            <div class="grid gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="material_type" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo de material</label>
                    <select id="material_type" name="material_type" class="field">
                        <option value="">Todos</option>
                        @foreach ($materialTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['material_type'] ?? null) === $type)>{{ (new \App\Models\Book(['material_type' => $type]))->materialTypeLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="category" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Classificação</label>
                    <select id="category" name="category" class="field">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="language" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Idioma</label>
                    <select id="language" name="language" class="field">
                        <option value="">Todos</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language }}" @selected(($filters['language'] ?? null) === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
                @if (($filters['collection_group'] ?? null) !== 'general')
                    <div>
                        <label for="education_stage" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Etapa de ensino</label>
                        <select id="education_stage" name="education_stage" class="field">
                            <option value="">Todas</option>
                            @foreach ($educationStages as $stage)
                                <option value="{{ $stage }}" @selected(($filters['education_stage'] ?? null) === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="publisher" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Editora</label>
                    <select id="publisher" name="publisher" class="field">
                        <option value="">Todas</option>
                        @foreach ($publishers as $publisher)
                            <option value="{{ $publisher }}" @selected(($filters['publisher'] ?? null) === $publisher)>{{ $publisher }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="year" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Ano</label>
                    <select id="year" name="year" class="field">
                        <option value="">Todos</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="collection" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Coleção</label>
                    <select id="collection" name="collection" class="field">
                        <option value="">Todas</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:col-span-2 lg:col-span-4">
                    <label class="flex min-h-11 items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="archived" value="1" @checked($filters['archived'] ?? false) class="rounded-sm border-slate-400 text-indigo-600"> Incluir arquivados
                    </label>
                    <button type="submit" class="btn btn-secondary">Aplicar filtros</button>
                    @if ($hasFilters)
                        <a href="{{ route('books.index', ['vista' => $view]) }}" class="btn btn-quiet">Limpar filtros</a>
                    @endif
                </div>
            </div>
        </details>
    </form>

    <div data-loading-indicator hidden class="mt-4 flex items-center gap-2 text-sm text-slate-600">
        <span class="carregando" role="status"><span class="carregando__meio"></span><span class="somente-leitor">Buscando</span></span> Buscando no catálogo…
    </div>

    <div class="mt-6 flex items-center justify-between border-b border-slate-300 pb-2">
        <p class="text-sm text-slate-600" aria-live="polite">
            @if ($books->count()) {{ $books->firstItem() }}–{{ $books->lastItem() }} de {{ $books->total() }} @endif
        </p>
        <div class="flex items-center gap-1" role="group" aria-label="Modo de exibição">
            <a href="{{ $q(['vista' => 'grade']) }}" aria-label="Grade de capas" @if ($view === 'grade') aria-current="true" @endif
               class="grid h-11 w-11 place-items-center rounded-md {{ $view === 'grade' ? 'bg-slate-200 text-slate-900' : 'text-slate-500 hover:bg-slate-100' }}"><x-shared.icon name="grid" class="h-5 w-5" /></a>
            <a href="{{ $q(['vista' => 'lista']) }}" aria-label="Lista" @if ($view === 'lista') aria-current="true" @endif
               class="grid h-11 w-11 place-items-center rounded-md {{ $view === 'lista' ? 'bg-slate-200 text-slate-900' : 'text-slate-500 hover:bg-slate-100' }}"><x-shared.icon name="rows" class="h-5 w-5" /></a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="mt-8">
            @if ($hasFilters)
                <x-shared.empty-state title="Nenhum livro corresponde à busca." text="Tente outros termos ou limpe os filtros." icon="book">
                    <a href="{{ route('books.index') }}" class="btn btn-secondary">Limpar filtros</a>
                </x-shared.empty-state>
            @else
                <x-shared.empty-state title="O catálogo ainda não tem livros." text="Escaneie o código de barras de um livro ou cadastre-o manualmente." icon="book" gato="dormindo">
                    @can('catalog.create')<a href="{{ route('books.scanner') }}" class="btn btn-primary">Escanear livro</a>@endcan
                </x-shared.empty-state>
            @endif
        </div>
    @else
        @php
            $availability = function ($book) {
                if ($book->copies_count === 0) return ['slate', 'Sem exemplares'];
                if ($book->available_copies_count > 0) return ['emerald', $book->available_copies_count.' de '.$book->copies_count.' disponível(is)'];
                return ['amber', 'Todos emprestados ('.$book->copies_count.')'];
            };
        @endphp

        @if ($view === 'grade')
            <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                @foreach ($books as $book)
                    @php([$tone, $text] = $availability($book))
                    <li class="efeito-patinha relative">
                        <x-shared.book-cover :book="$book" class="w-full" />
                        <h2 class="mt-3 font-display text-[0.95rem] leading-snug">
                            <a href="{{ route('books.show', $book) }}" class="after:absolute after:inset-0 hover:text-indigo-700">{{ $book->title }}</a>
                        </h2>
                        @if ($book->authors)<p class="mt-0.5 truncate text-xs text-slate-600">{{ $book->authors }}</p>@endif
                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ collect([$book->publisher, $book->publication_year])->filter()->implode(' · ') }}</p>
                        @if ($book->isbn13 ?? $book->isbn10)<p class="catalog-id mt-1 truncate">ISBN {{ $book->isbn13 ?? $book->isbn10 }}</p>@endif
                        <p class="mt-2 flex flex-wrap gap-1">
                            <x-shared.badge :tone="$book->collection_group === 'school' ? 'sky' : 'indigo'">{{ $book->collectionGroupLabel() }}</x-shared.badge>
                            @if ($book->materialTypeLabel())<x-shared.badge>{{ $book->materialTypeLabel() }}</x-shared.badge>@endif
                            <x-shared.badge :tone="$tone">{{ $text }}</x-shared.badge>
                            @if ($book->isArchived())<x-shared.badge>Arquivado</x-shared.badge>@endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @else
            <ul class="mt-2">
                @foreach ($books as $book)
                    @php([$tone, $text] = $availability($book))
                    <li class="relative flex gap-4 border-b border-slate-200 py-4">
                        <x-shared.book-cover :book="$book" class="w-16 shrink-0 sm:w-20" />
                        <div class="min-w-0 flex-1">
                            <h2 class="font-display text-lg leading-snug">
                                <a href="{{ route('books.show', $book) }}" class="after:absolute after:inset-0 hover:text-indigo-700">{{ $book->title }}</a>
                            </h2>
                            @if ($book->authors)<p class="mt-0.5 truncate text-sm text-slate-700">{{ $book->authors }}</p>@endif
                            <p class="mt-0.5 text-xs text-slate-500">{{ collect([$book->publisher, $book->edition ? $book->edition.' ed.' : null, $book->publication_year])->filter()->implode(' · ') }}</p>
                            @if ($book->isbn13 ?? $book->isbn10)<p class="catalog-id mt-1">ISBN {{ $book->isbn13 ?? $book->isbn10 }}</p>@endif
                            <p class="mt-2 flex flex-wrap gap-1.5">
                                <x-shared.badge :tone="$book->collection_group === 'school' ? 'sky' : 'indigo'">{{ $book->collectionGroupLabel() }}</x-shared.badge>
                                @if ($book->materialTypeLabel())<x-shared.badge>{{ $book->materialTypeLabel() }}</x-shared.badge>@endif
                                <x-shared.badge :tone="$tone">{{ $text }}</x-shared.badge>
                                @if ($book->isArchived())<x-shared.badge>Arquivado</x-shared.badge>@endif
                                @foreach ($book->categories->take(2) as $category)
                                    <span class="rounded-sm bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $category->name }}</span>
                                @endforeach
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8">{{ $books->links() }}</div>
    @endif
@endsection
