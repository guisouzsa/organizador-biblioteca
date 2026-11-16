@extends('layouts.app')

@section('title', 'Catálogo — '.config('app.name'))

@php
    $select = 'mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600';
    $hasFilters = collect($filters)->filter()->isNotEmpty();
@endphp

@section('content')
    <x-shared.page-header title="Catálogo" :subtitle="$books->total().' '.($books->total() === 1 ? 'livro' : 'livros').($hasFilters ? ' encontrados' : ' cadastrados')">
        @can('catalog.create')
            <a href="{{ route('books.scanner') }}" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Escanear livro</a>
            <a href="{{ route('books.create') }}" class="rounded-md px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Cadastro manual</a>
        @endcan
    </x-shared.page-header>

    <form method="GET" action="{{ route('books.index') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <label for="q" class="block text-sm font-medium text-slate-700">Buscar</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Título, autor ou ISBN" class="{{ $select }}">
        </div>
        <div>
            <label for="category" class="block text-sm font-medium text-slate-700">Disciplina</label>
            <select id="category" name="category" class="{{ $select }}">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="publisher" class="block text-sm font-medium text-slate-700">Editora</label>
            <select id="publisher" name="publisher" class="{{ $select }}">
                <option value="">Todas</option>
                @foreach ($publishers as $publisher)
                    <option value="{{ $publisher }}" @selected(($filters['publisher'] ?? null) === $publisher)>{{ $publisher }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="year" class="block text-sm font-medium text-slate-700">Ano</label>
            <select id="year" name="year" class="{{ $select }}">
                <option value="">Todos</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="collection" class="block text-sm font-medium text-slate-700">Coleção</label>
            <select id="collection" name="collection" class="{{ $select }}">
                <option value="">Todas</option>
                @foreach ($collections as $collection)
                    <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="archived" value="1" @checked($filters['archived'] ?? false) class="rounded border-slate-300 text-indigo-600"> Incluir livros arquivados
            </label>
            @if ($hasFilters)
                <a href="{{ route('books.index') }}" class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Limpar filtros</a>
            @endif
        </div>
    </form>

    @if ($books->isEmpty())
        <div class="mt-8">
            @if ($hasFilters)
                <x-shared.empty-state title="Nenhum livro corresponde aos filtros." text="Tente outros termos ou limpe os filtros." icon="book" />
            @else
                <x-shared.empty-state title="O catálogo ainda não tem livros." text="Escaneie o código de barras de um livro ou cadastre-o manualmente." icon="book">
                    @can('catalog.create')<a href="{{ route('books.scanner') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Escanear livro</a>@endcan
                </x-shared.empty-state>
            @endif
        </div>
    @else
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($books as $book)
                <li class="relative flex gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-indigo-300">
                    <x-shared.book-cover :book="$book" class="h-36 w-24 shrink-0" />
                    <div class="min-w-0">
                        <h2 class="font-semibold leading-snug">
                            <a href="{{ route('books.show', $book) }}" class="after:absolute after:inset-0 hover:text-indigo-700">{{ $book->title }}</a>
                        </h2>
                        @if ($book->authors)
                            <p class="mt-1 truncate text-sm text-slate-600">{{ $book->authors }}</p>
                        @endif
                        <p class="mt-1 text-sm text-slate-500">
                            {{ collect([$book->publisher, $book->publication_year])->filter()->implode(' · ') }}
                        </p>
                        @if ($book->isbn13 ?? $book->isbn10)
                            <p class="mt-1 font-mono text-xs text-slate-500">ISBN {{ $book->isbn13 ?? $book->isbn10 }}</p>
                        @endif
                        <p class="mt-2 flex flex-wrap gap-1">
                            @if ($book->isArchived())<x-shared.badge tone="slate">Arquivado</x-shared.badge>@endif
                            @if ($book->copies_count === 0)
                                <x-shared.badge tone="slate">Sem exemplares</x-shared.badge>
                            @elseif ($book->available_copies_count > 0)
                                <x-shared.badge tone="emerald">{{ $book->available_copies_count }} de {{ $book->copies_count }} disponível(is)</x-shared.badge>
                            @else
                                <x-shared.badge tone="amber">Nenhum disponível ({{ $book->copies_count }})</x-shared.badge>
                            @endif
                            @foreach ($book->categories->take(3) as $category)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $category->name }}</span>
                            @endforeach
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8">{{ $books->links() }}</div>
    @endif
@endsection
