{{-- Resultado da consulta por ISBN, inserido na tela do scanner. --}}
@php
    $status = $result['status'];
    $isbn13 = $result['isbn13'];
@endphp

@if ($status === 'invalid')
    <x-ui.alert tone="error" title="ISBN inválido">
        {{ $result['message'] }} Confira o número ou tente ler o código novamente.
    </x-ui.alert>

@elseif ($status === 'exists')
    @php
        $book = $result['book'];
    @endphp
    <div class="panel p-4 sm:p-5">
        <x-ui.alert tone="warning" title="Este livro já está no catálogo">Não é preciso cadastrá-lo de novo. Abra o registro ou atualize os dados.</x-ui.alert>
        <div class="mt-4 flex gap-4">
            <x-shared.book-cover :book="$book" class="w-24 shrink-0 sm:w-28" />
            <div class="min-w-0 text-sm">
                <p class="text-base font-semibold">{{ $book->title }}</p>
                @if ($book->authors)<p class="text-slate-700">{{ $book->authors }}</p>@endif
                <p class="catalog-id mt-1">ISBN {{ $book->isbn13 ?? $book->isbn10 }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('books.show', $book) }}" class="btn btn-secondary">Abrir registro</a>
                    <a href="{{ route('books.edit', $book) }}" class="btn btn-primary">Editar / atualizar dados</a>
                </div>
            </div>
        </div>
    </div>

@elseif ($status === 'not_found' || $status === 'unavailable')
    <x-ui.alert tone="warning" :title="$status === 'not_found' ? 'Livro não encontrado' : 'Fontes indisponíveis'">
        {{ $result['message'] }}
        <span class="catalog-id mt-1 block">ISBN {{ $isbn13 }}</span>
        <a href="{{ route('books.create', ['isbn' => $isbn13]) }}" class="btn btn-primary mt-3">Cadastrar manualmente com este ISBN</a>
    </x-ui.alert>

@elseif ($status === 'found')
    @php
        $cover = $values['cover_url'] ?? null;
        $coverSource = null;
        foreach ($result['sources'] as $source) {
            if ($cover && ($source['cover_url'] ?? null) === $cover) {
                $coverSource = \App\Models\BookSource::providerLabel($source['provider']);
                break;
            }
        }
    @endphp
    <div class="space-y-5">
        {{-- Resumo: capa + dados encontrados, antes do formulário editável --}}
        <section class="panel p-4 sm:p-5" aria-labelledby="achado">
            <div class="flex gap-4 sm:gap-5">
                <figure class="w-24 shrink-0 sm:w-32">
                    @if ($cover)
                        <img src="{{ $cover }}"
                             alt="Capa de {{ $values['title'] ?? 'livro' }}"
                             width="200" height="300"
                             referrerpolicy="no-referrer"
                             decoding="async"
                             loading="eager"
                             class="cover w-full"
                             onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:this.className.replace('cover','').trim()+' cover cover--empty',role:'img',innerHTML:'<span class=\'cover__label\'>Capa indisponível</span>'}))">
                        @if ($coverSource)<figcaption class="mt-1.5 text-center text-[0.6875rem] text-slate-500">Capa: {{ $coverSource }}</figcaption>@endif
                    @else
                        <div class="cover cover--empty w-full" role="img" aria-label="Nenhuma fonte trouxe a capa deste livro"><span class="cover__label">Capa não encontrada nas fontes</span></div>
                    @endif
                </figure>
                <div class="min-w-0">
                    <p class="eyebrow">Livro Encontrado</p>
                    <h2 id="achado" class="mt-0.5 text-xl leading-snug">{{ $values['title'] ?? 'Sem título' }}</h2>
                    @if (! empty($values['authors']))<p class="mt-1 text-sm text-slate-700">{{ $values['authors'] }}</p>@endif
                    <p class="mt-1 text-xs text-slate-600">{{ collect([$values['publisher'] ?? null, $values['publication_year'] ?? null])->filter()->implode(' · ') }}</p>
                    <p class="catalog-id mt-1">ISBN {{ $isbn13 }}</p>
                    <p class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                        Fontes:
                        @foreach ($result['sources'] as $source)
                            <span class="rounded-sm bg-slate-100 px-2 py-0.5 font-medium text-slate-700">{{ \App\Models\BookSource::providerLabel($source['provider']) }}</span>
                        @endforeach
                    </p>
                </div>
            </div>
            <p class="mt-4 border-t border-cinza-5 pt-3 text-sm text-slate-600">Confira os dados com o livro em mãos. Todos os campos abaixo podem ser editados e nada é gravado até você confirmar.</p>
            @if (! $cover)
                <p class="mt-1 text-sm text-slate-600">Sem capa não há problema: o livro pode ser salvo normalmente e a capa adicionada depois.</p>
            @endif
        </section>

        @foreach ($result['errors'] as $provider => $error)
            <x-ui.alert tone="info">{{ \App\Models\BookSource::providerLabel($provider) }} não respondeu agora. Os dados acima vêm das demais fontes e continuam editáveis.</x-ui.alert>
        @endforeach

        @if ($result['divergences'])
            <x-ui.alert tone="warning" title="Divergência entre fontes">
                As fontes trazem valores diferentes. Corrija os campos conforme o livro físico.
                <div class="mt-3 overflow-x-auto">
                    <table data-keep-table class="w-full text-left text-xs">
                        <thead><tr>
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
            </x-ui.alert>
        @endif

        <div class="panel p-4 sm:p-6">
            @include('catalog.partials.form', [
                'action' => route('books.store'),
                'method' => 'POST',
                'submitLabel' => 'Confirmar e salvar no catálogo',
            ])
        </div>
    </div>
@endif
