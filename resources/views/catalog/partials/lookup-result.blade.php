{{-- Resultado da consulta por ISBN, inserido na tela do scanner. --}}
@php
    $status = $result['status'];
    $isbn13 = $result['isbn13'];
@endphp

@if ($status === 'invalid')
    <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p class="font-semibold">ISBN inválido</p>
        <p class="mt-1">{{ $result['message'] }}</p>
        <p class="mt-1 text-rose-700">Confira o número ou tente ler o código novamente.</p>
    </div>

@elseif ($status === 'exists')
    @php($book = $result['book'])
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-900">Este livro já está no catálogo</p>
        <div class="mt-3 flex gap-4">
            <x-shared.book-cover :book="$book" class="h-28 w-20 shrink-0" />
            <div class="min-w-0 text-sm">
                <p class="font-semibold">{{ $book->title }}</p>
                @if ($book->authors)<p class="text-slate-700">{{ $book->authors }}</p>@endif
                <p class="font-mono text-xs text-slate-600">ISBN {{ $book->isbn13 ?? $book->isbn10 }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('books.show', $book) }}" class="rounded-md bg-white px-3 py-2 font-medium ring-1 ring-inset ring-amber-300 hover:bg-amber-100">Abrir registro</a>
                    <a href="{{ route('books.edit', $book) }}" class="rounded-md bg-amber-600 px-3 py-2 font-medium text-white hover:bg-amber-700">Editar / atualizar dados</a>
                </div>
            </div>
        </div>
    </div>

@elseif ($status === 'not_found' || $status === 'unavailable')
    <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">{{ $status === 'not_found' ? 'Livro não encontrado' : 'Fontes indisponíveis' }}</p>
        <p class="mt-1">{{ $result['message'] }}</p>
        <p class="mt-1 font-mono text-xs">ISBN {{ $isbn13 }}</p>
        <a href="{{ route('books.create', ['isbn' => $isbn13]) }}" class="mt-3 inline-block rounded-md bg-amber-600 px-3 py-2 font-medium text-white hover:bg-amber-700">Cadastrar manualmente com este ISBN</a>
    </div>

@elseif ($status === 'found')
    <div class="space-y-5">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            <p class="font-semibold">Resultado encontrado — confira os dados antes de salvar</p>
            <p class="mt-1">
                Fontes consultadas:
                @foreach ($result['sources'] as $source)
                    <span class="mr-1 inline-block rounded-md bg-white px-2 py-0.5 text-xs font-medium ring-1 ring-emerald-300">{{ \App\Models\BookSource::providerLabel($source['provider']) }}</span>
                @endforeach
            </p>
            @foreach ($result['errors'] as $provider => $error)
                <p class="mt-1 text-amber-800">{{ \App\Models\BookSource::providerLabel($provider) }} não respondeu e foi ignorada.</p>
            @endforeach
        </div>

        @if ($result['divergences'])
            <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Divergência entre fontes</p>
                <p class="mt-1">As fontes trazem valores diferentes. Corrija os campos abaixo conforme o livro físico.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead><tr class="text-amber-800">
                            <th class="pb-1 pr-4 font-medium">Campo</th>
                            @foreach ($result['sources'] as $source)
                                <th class="pb-1 pr-4 font-medium">{{ \App\Models\BookSource::providerLabel($source['provider']) }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                        @foreach ($result['divergences'] as $divergence)
                            <tr class="border-t border-amber-200">
                                <td class="py-1 pr-4 font-medium">{{ $divergence['label'] }}</td>
                                @foreach ($result['sources'] as $source)
                                    <td class="py-1 pr-4">{{ $divergence['values'][$source['provider']] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="panel flex gap-4 p-4">
            @if (! empty($values['cover_url']))
                <img src="{{ $values['cover_url'] }}" alt="Capa encontrada" referrerpolicy="no-referrer" class="h-40 w-28 shrink-0 rounded-md bg-slate-100 object-cover shadow-sm">
            @endif
            <p class="self-center text-sm text-slate-600">Todos os campos podem ser editados antes de salvar. Nada é gravado até você confirmar.</p>
        </div>

        <div class="panel p-6">
            @include('catalog.partials.form', [
                'action' => route('books.store'),
                'method' => 'POST',
                'submitLabel' => 'Confirmar e salvar no catálogo',
            ])
        </div>
    </div>
@endif
