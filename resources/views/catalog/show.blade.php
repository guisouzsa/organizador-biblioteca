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
        'Grupo do acervo' => $book->collectionGroupLabel(),
        'Tipo de material' => $book->materialTypeLabel(),
        'Público / faixa etária' => $book->audience,
        'Etapa de ensino' => $book->education_stage,
        'Coleção' => $book->collection?->name,
    ];
@endphp

@section('content')
    <nav aria-label="Navegação estrutural" class="mb-4 text-sm">
        <a href="{{ route('books.index') }}" class="inline-flex min-h-11 items-center text-indigo-700 hover:underline">← Voltar ao catálogo</a>
    </nav>

    <article class="grid gap-6 md:grid-cols-[11rem_1fr] md:gap-10">
        <div class="marca-pagina mx-auto w-36 md:sticky md:top-8 md:w-full md:self-start">
            <x-shared.book-cover :book="$book" class="w-full" />
        </div>

        <div class="min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="eyebrow mb-1">Ficha bibliográfica</p>
                    <h1 class="text-3xl leading-tight">{{ $book->title }}</h1>
                    <p class="mt-2 flex flex-wrap gap-1.5">
                        <x-shared.badge :tone="$book->collection_group === 'school' ? 'sky' : 'indigo'">{{ $book->collectionGroupLabel() }}</x-shared.badge>
                        @if ($book->materialTypeLabel())<x-shared.badge>{{ $book->materialTypeLabel() }}</x-shared.badge>@endif
                    </p>
                    @if ($book->isArchived())<x-shared.badge tone="slate" class="mt-1">Arquivado em {{ $book->archived_at->format('d/m/Y') }}</x-shared.badge>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $book)<a href="{{ route('books.edit', $book) }}" class="btn btn-secondary">Editar</a>@endcan
                    @can('archive', $book)
                        <form method="POST" action="{{ route($book->isArchived() ? 'books.restore' : 'books.archive', $book) }}" data-confirm="{{ $book->isArchived() ? '“'.$book->title.'” voltará a aparecer no catálogo.' : '“'.$book->title.'” deixa de aparecer na busca padrão. Os exemplares continuam no acervo.' }}" data-confirm-title="{{ $book->isArchived() ? 'Restaurar este livro?' : 'Arquivar este livro?' }}" data-confirm-label="{{ $book->isArchived() ? 'Restaurar' : 'Arquivar' }}">
                            @csrf
                            <button class="btn btn-secondary">{{ $book->isArchived() ? 'Restaurar' : 'Arquivar' }}</button>
                        </form>
                    @endcan
                    @can('delete', $book)
                        <form method="POST" action="{{ route('books.destroy', $book) }}" data-confirm="“{{ $book->title }}” será excluído do catálogo. Esta ação não pode ser desfeita." data-confirm-title="Excluir este livro?" data-confirm-label="Excluir livro" data-confirm-danger">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger">Excluir</button>
                        </form>
                    @endcan
                </div>
            </div>

            <dl class="mt-6 grid gap-x-8 gap-y-4 border-t border-slate-300 pt-5 text-sm sm:grid-cols-2">
                @foreach ($rows as $label => $value)
                    @if (filled($value))
                        <div>
                            <dt class="eyebrow">{{ $label }}</dt>
                            <dd class="mt-0.5 text-[0.95rem] {{ str_starts_with($label, 'ISBN') ? 'catalog-id !text-sm !text-slate-900' : '' }}">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>

            @if ($book->categories->isNotEmpty())
                <p class="mt-4 flex flex-wrap gap-1">
                    @foreach ($book->categories as $category)
                        <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $category->name }} · {{ $category->type->label() }}</span>
                    @endforeach
                </p>
            @endif

            @if ($book->description)
                <div class="mt-6">
                    <h2 class="eyebrow !font-sans">Descrição</h2>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $book->description }}</p>
                </div>
            @endif

            {{-- Exemplares físicos --}}
            @can('copy.view')
                <section class="mt-8" aria-labelledby="exemplares">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="exemplares" class="text-xl">
                            Exemplares <span class="font-normal text-slate-500">({{ $availableCount }} disponível(is) de {{ $copiesCount }})</span>
                            @if ($reservationsCount > 0)<x-shared.badge tone="sky" class="ml-1">{{ $reservationsCount }} reserva(s)</x-shared.badge>@endif
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            @can('create', \App\Models\BookCopy::class)
                                @unless ($book->isArchived())
                                    <a href="{{ route('books.copies.create', $book) }}" class="btn btn-primary">Registrar exemplares</a>
                                @endunless
                            @endcan
                            @if ($copiesCount > 0 && $availableCount === 0)
                                @can('reservation.create')<a href="{{ route('reservations.index', ['livro' => $book->id]) }}" class="btn btn-secondary">Reservar</a>@endcan
                            @endif
                        </div>
                    </div>

                    @if ($copies->isEmpty())
                        <div class="mt-3">
                            <x-shared.empty-state title="Este livro ainda não tem exemplares." text="O catálogo registra a obra; os exemplares são as cópias físicas que a escola possui." icon="stack" />
                        </div>
                    @else
                        <div class="panel mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-4 py-2">Código</th><th class="px-4 py-2">Local</th><th class="px-4 py-2">Conservação</th><th class="px-4 py-2">Situação</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($copies as $copy)
                                        @php($s = $copy->situation())
                                        <tr>
                                            <td class="catalog-id px-4 py-2"><a href="{{ route('copies.show', $copy) }}" class="text-indigo-700 hover:underline">{{ $copy->code() }}</a></td>
                                            <td class="px-4 py-2">{{ $copy->location?->fullName() ?? '—' }}</td>
                                            <td class="px-4 py-2">{{ $copy->condition->label() }}</td>
                                            <td class="px-4 py-2">
                                                <x-shared.badge :tone="$s['tone']">{{ $s['label'] }}</x-shared.badge>
                                                @if ($copy->activeLoan)<span class="ml-1 text-xs text-slate-500">até {{ $copy->activeLoan->due_on->format('d/m') }}</span>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endcan

            <hr class="divisor-patas" aria-hidden="true">

            <section aria-labelledby="fontes">
                <h2 id="fontes" class="text-xl">Fontes das informações</h2>
                <ul class="panel mt-2 divide-y divide-slate-200 text-sm">
                    @forelse ($book->sources as $source)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <p class="font-medium">{{ \App\Models\BookSource::providerLabel($source->provider) }}</p>
                                <p class="text-xs text-slate-500">
                                    @if ($source->retrieved_at) Consultado em {{ $source->retrieved_at->format('d/m/Y H:i') }} @endif
                                    @if (preg_match('#^https?://#i', (string) $source->source_url)) · <a href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-700 underline">ver origem</a> @endif
                                </p>
                            </div>
                            <span class="rounded-md px-2.5 py-1 text-xs font-medium {{ $source->verification_status === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
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
