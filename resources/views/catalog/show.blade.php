@extends('layouts.app')

@section('title', $book->title.' — '.config('app.name'))

@php
    $rows = [
        'Subtítulo' => $book->subtitle,
        'Autores' => $book->authors,
        'Editora' => $book->publisher,
        'Edição' => $book->edition,
        'Ano de publicação' => $book->publication_year,
        'Páginas' => $book->pages,
        'ISBN-13' => $book->isbn13,
        'ISBN-10' => $book->isbn10,
        'Idioma' => $book->language,
        'Formato' => $book->format,
        'Coleção' => $book->collection?->name,
    ];
@endphp

@section('content')
    <nav aria-label="Navegação estrutural" class="mb-4 text-sm">
        <a href="{{ route('books.index') }}" class="text-indigo-700 hover:underline">← Voltar ao catálogo</a>
    </nav>

    <article class="grid gap-8 md:grid-cols-[auto_1fr]">
        <x-shared.book-cover :book="$book" class="h-64 w-44" />

        <div>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $book->title }}</h1>
                <a href="{{ route('books.edit', $book) }}" class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Editar</a>
            </div>

            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @foreach ($rows as $label => $value)
                    @if (filled($value))
                        <div>
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="font-medium {{ str_starts_with($label, 'ISBN') ? 'font-mono' : '' }}">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>

            @if ($book->categories->isNotEmpty())
                <p class="mt-4 flex flex-wrap gap-1">
                    @foreach ($book->categories as $category)
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $category->name }}</span>
                    @endforeach
                </p>
            @endif

            @if ($book->description)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-slate-700">Descrição</h2>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $book->description }}</p>
                </div>
            @endif

            <section class="mt-8" aria-labelledby="fontes">
                <h2 id="fontes" class="text-sm font-semibold text-slate-700">Fontes das informações</h2>
                <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white text-sm ring-1 ring-slate-200">
                    @forelse ($book->sources as $source)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <p class="font-medium">{{ \App\Models\BookSource::providerLabel($source->provider) }}</p>
                                <p class="text-xs text-slate-500">
                                    @if ($source->retrieved_at) Consultado em {{ $source->retrieved_at->format('d/m/Y H:i') }} @endif
                                    @if ($source->source_url) · <a href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-700 underline">ver origem</a> @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $source->verification_status === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $source->verification_status === 'confirmed' ? 'Conferido pelo usuário' : 'Não verificado' }}
                            </span>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-slate-500">Nenhuma fonte registrada.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </article>
@endsection
