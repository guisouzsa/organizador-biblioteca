{{--
    Formulário de livro (cadastro manual, confirmação do scanner e edição).
    Variáveis: $action, $method, $values, $categories, $collections (id => nome), $submitLabel,
               $duplicate (Book|null), $similar (Collection), $lookupToken, $suggestedCategories (opcional),
               $book (Book|null, só na edição), $hasCopies (opcional)
    Nada aqui preenche dados por suposição: só o que foi informado ou encontrado nas fontes (e conferido pelo operador).
--}}
@php
    $selectedCategories = array_map('intval', (array) old('categories', $values['categories'] ?? []));
    $suggestedCategories = $suggestedCategories ?? [];
    $similar = $similar ?? collect();
    $book = $book ?? null;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-8" novalidate data-once data-dirty-guard data-book-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($lookupToken)
        <input type="hidden" name="lookup_token" value="{{ $lookupToken }}">
    @endif
    @if ($book)
        <input type="hidden" name="version" value="{{ $book->updated_at->timestamp }}">
    @endif

    @if ($duplicate)
        <x-ui.alert tone="warning" title="Este ISBN já está no catálogo">
            <a href="{{ route('books.show', $duplicate) }}" class="font-medium underline">{{ $duplicate->title }}</a>
            já usa o ISBN informado. Nenhum registro duplicado foi criado.
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('books.show', $duplicate) }}" class="btn btn-secondary">Abrir registro existente</a>
                @can('update', $duplicate)
                    <button type="submit" name="confirm_update" value="1" class="btn btn-primary">Atualizar o registro existente com estes dados</button>
                @endcan
            </div>
        </x-ui.alert>
    @endif

    @if ($similar->isNotEmpty())
        <x-ui.alert tone="warning" title="Este livro pode já estar cadastrado (sem ISBN)">
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($similar as $candidate)
                    <li><a href="{{ route('books.show', $candidate) }}" class="font-medium underline">{{ $candidate->title }}</a>
                        <span>— {{ collect([$candidate->authors, $candidate->publisher, $candidate->publication_year])->filter()->implode(' · ') ?: 'sem outros dados' }}</span></li>
                @endforeach
            </ul>
            <button type="submit" name="confirm_distinct" value="1" class="btn btn-secondary mt-3">É outro livro: salvar mesmo assim</button>
        </x-ui.alert>
    @endif

    <p class="text-xs text-slate-600"><span class="text-rose-700" aria-hidden="true">*</span> Campo obrigatório. Os demais são opcionais.</p>

    <fieldset class="space-y-4">
        <legend class="eyebrow mb-1">Identificação</legend>
        <x-shared.input name="title" label="Título" :value="$values['title'] ?? ''" required />
        <x-shared.input name="subtitle" label="Subtítulo" :value="$values['subtitle'] ?? ''" />
        <x-shared.input name="authors" label="Autores" :value="$values['authors'] ?? ''" hint="Separe vários autores com ponto e vírgula." />
        <x-shared.input name="isbn" label="ISBN (10 ou 13)" :value="$values['isbn'] ?? ''" inputmode="text" autocomplete="off"
                        hint="Opcional. Livros sem ISBN podem ser cadastrados normalmente." />
    </fieldset>

    <fieldset class="space-y-4">
        <legend class="eyebrow mb-1">Publicação</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-shared.input name="publisher" label="Editora" :value="$values['publisher'] ?? ''" />
            <x-shared.input name="edition" label="Edição" :value="$values['edition'] ?? ''" />
            <x-shared.input name="publication_year" label="Ano de publicação" type="number" :value="$values['publication_year'] ?? ''" inputmode="numeric" />
            <x-shared.input name="pages" label="Páginas" type="number" :value="$values['pages'] ?? ''" inputmode="numeric" />
            <x-shared.input name="language" label="Idioma" :value="$values['language'] ?? ''" hint="Ex.: pt-BR, por, en" />
            <x-shared.input name="format" label="Formato" :value="$values['format'] ?? ''" hint="Ex.: Brochura, Livro digital" />
        </div>
    </fieldset>

    <fieldset class="space-y-4">
        <legend class="eyebrow mb-1">Capa e descrição</legend>
        <x-shared.input name="cover_url" label="URL da capa" type="url" :value="$values['cover_url'] ?? ''" inputmode="url"
                        hint="Opcional. Só use um endereço que você tenha conferido." />
        <x-shared.textarea name="description" label="Descrição" rows="4" :value="$values['description'] ?? ''" />
    </fieldset>

    <fieldset class="space-y-4">
        <legend class="eyebrow mb-1">Classificação</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-shared.select name="collection_group" label="Grupo do acervo" data-collection-group
                :options="['general' => 'Acervo geral', 'school' => 'Acervo escolar']"
                :value="$values['collection_group'] ?? 'general'" />
            <x-shared.select name="material_type" label="Tipo de material"
                :options="[
                    'textbook' => 'Livro didático', 'support' => 'Material de apoio',
                    'literature' => 'Literatura', 'technical' => 'Técnico',
                    'academic' => 'Acadêmico', 'reference' => 'Referência',
                    'popular_science' => 'Divulgação científica', 'other' => 'Outro',
                ]"
                :value="$values['material_type'] ?? ''" placeholder="Não informado" />
            <x-shared.input name="audience" label="Público / faixa etária" :value="$values['audience'] ?? ''" hint="Opcional. Ex.: infantil, juvenil, adulto." />
            <x-shared.input name="education_stage" label="Etapa de ensino" :value="$values['education_stage'] ?? ''" hint="Use apenas quando se aplicar ao material." class="sm:col-span-2" data-school-field />
        </div>
        <div>
            <p class="mb-1 text-sm font-medium text-slate-800">Disciplinas, assuntos e gêneros</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md bg-white px-3 py-1.5 text-sm ring-1 ring-inset ring-cinza-1 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-800 has-[:checked]:ring-2 has-[:checked]:ring-indigo-600 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-indigo-600">
                        <input type="checkbox" name="categories[]" value="{{ $category->id }}" class="sr-only" @checked(in_array($category->id, $selectedCategories, true))>
                        {{ $category->name }} <span class="text-xs text-slate-500">({{ $category->type->label() }})</span>
                    </label>
                @endforeach
            </div>
        </div>
        @can('category.manage')
            <x-shared.input name="new_categories" label="Outras categorias" :value="''"
                            hint="Crie categorias adicionais separando por vírgula.{{ $suggestedCategories ? ' Sugeridas pelas fontes: '.implode(', ', array_slice($suggestedCategories, 0, 5)).'.' : '' }}" />
        @endcan
        <x-shared.select name="collection_id" label="Coleção didática" :options="$collections" :value="$values['collection_id'] ?? ''" placeholder="Nenhuma"
                         hint="Coleções são cadastradas em Administração → Disciplinas e categorias." />
    </fieldset>

    @if (! empty($hasCopies))
        <label class="flex items-start gap-3 rounded-lg bg-slate-100 p-3 text-sm text-slate-700">
            <input type="checkbox" name="confirm_isbn_change" value="1" class="mt-0.5 rounded-sm border-slate-400 text-indigo-600">
            <span>Confirmo que, se eu alterar o ISBN, estou ciente de que este livro <strong>já tem exemplares</strong> cadastrados.</span>
        </label>
    @endif

    {{-- Barra de ações: fixa no rodapé do celular para ficar ao alcance do polegar --}}
    <div class="sticky bottom-[4.25rem] z-10 -mx-4 flex flex-col-reverse gap-2 border-t border-cinza-5 bg-white px-4 py-3 sm:static sm:mx-0 sm:flex-row sm:border-0 sm:bg-transparent sm:px-0 sm:pb-0 sm:pt-2 lg:bottom-0">
        <a href="{{ route('books.index') }}" data-discard class="btn btn-secondary max-sm:w-full">Cancelar</a>
        <button type="submit" class="btn btn-primary max-sm:w-full">{{ $submitLabel }}</button>
    </div>
</form>
