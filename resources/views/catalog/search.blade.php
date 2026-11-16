@extends('layouts.app')

@section('title', 'Identificar livro — '.config('app.name'))

@section('content')
    <x-shared.page-header title="Identificar livro" subtitle="Pesquise por título, autor, editora, ISBN ou assunto. O catálogo local aparece primeiro." />

    <form method="GET" action="{{ route('books.search') }}" class="panel mt-6 flex flex-col gap-3 p-4 sm:flex-row">
        <label for="bibliographic-query" class="sr-only">Termos da pesquisa</label>
        <input id="bibliographic-query" name="q" value="{{ $query }}" class="field flex-1" maxlength="160"
               placeholder="Ex.: Dom Casmurro Machado de Assis" required autofocus>
        <button class="btn btn-primary" type="submit">Pesquisar fontes</button>
    </form>

    <details class="panel mt-4 p-4">
        <summary class="cursor-pointer font-semibold">Fotografar capa ou página de créditos</summary>
        <form method="POST" action="{{ route('books.identify-image') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-[1fr_auto]">
            @csrf
            <div class="grid gap-3">
                <input class="field" type="file" name="image" accept="image/jpeg,image/png,image/webp" capture="environment" required>
                <select class="field" name="image_type" required>
                    <option value="cover">Capa</option>
                    <option value="credits">Página de créditos ou ficha catalográfica</option>
                </select>
                <p class="text-xs text-slate-600">A imagem é processada apenas para extrair termos de pesquisa. Revise sempre o resultado antes de cadastrar.</p>
            </div>
            <button class="btn btn-secondary self-end" type="submit">Extrair e pesquisar</button>
        </form>
    </details>

    @if ($query !== '')
        @if ($local)
            <section class="mt-8">
                <h2 class="text-xl font-semibold">No catálogo local</h2>
                <ul class="mt-3 divide-y divide-slate-200 border-y border-slate-200">
                    @foreach ($local as $book)
                        <li class="py-3"><a class="font-semibold underline" href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                            @if ($book->authors)<p class="text-sm text-slate-600">{{ $book->authors }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Fontes bibliográficas</h2>
            @if ($external)
                <ul class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach ($external as $item)
                        <li class="panel flex gap-4 p-4">
                            @if ($item['cover_url'] ?? null)<img src="{{ $item['cover_url'] }}" alt="" class="h-28 w-20 shrink-0 object-cover" referrerpolicy="no-referrer">@endif
                            <div class="min-w-0">
                                <h3 class="font-semibold">{{ $item['title'] }}</h3>
                                @if ($item['authors'] ?? null)<p class="text-sm text-slate-600">{{ implode('; ', $item['authors']) }}</p>@endif
                                <p class="mt-1 text-xs text-slate-500">{{ collect([$item['publisher'] ?? null, $item['publication_year'] ?? null, $item['language'] ?? null])->filter()->implode(' · ') }}</p>
                                <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $item['provider']) }}</p>
                                <a class="btn btn-secondary mt-3" href="{{ route('books.create', ['lookup_token' => $item['lookup_token']]) }}">Revisar e cadastrar</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-shared.empty-state class="mt-4" title="Nenhuma fonte encontrou resultados." text="Tente outros termos ou faça o cadastro manual." icon="search" />
            @endif
        </section>

        @if ($errors)
            <x-ui.alert tone="warning" title="Algumas fontes não responderam" class="mt-6">
                O cadastro manual continua disponível.
            </x-ui.alert>
        @endif
    @endif

    <div class="mt-8"><a href="{{ route('books.create') }}" class="btn btn-quiet">Cadastrar sem consultar fontes</a></div>
@endsection
