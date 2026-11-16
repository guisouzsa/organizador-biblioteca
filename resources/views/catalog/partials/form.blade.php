{{--
    Formulário de livro (cadastro manual, confirmação do scanner e edição).
    Variáveis: $action, $method, $values, $categories, $collections, $submitLabel,
               $duplicate (Book|null), $lookupToken, $suggestedCategories (opcional)
--}}
@php
    $selectedCategories = array_map('intval', (array) old('categories', $values['categories'] ?? []));
    $suggestedCategories = $suggestedCategories ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($lookupToken)
        <input type="hidden" name="lookup_token" value="{{ $lookupToken }}">
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Corrija os campos destacados antes de salvar.
        </div>
    @endif

    @if ($duplicate)
        <div role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Este ISBN já está no catálogo.</p>
            <p class="mt-1">
                <a href="{{ route('books.show', $duplicate) }}" class="font-medium underline">{{ $duplicate->title }}</a>
                já usa o ISBN informado. Nenhum registro duplicado foi criado.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('books.show', $duplicate) }}" class="rounded-md bg-white px-3 py-2 text-sm font-medium ring-1 ring-inset ring-amber-300 hover:bg-amber-100">Abrir registro existente</a>
                <button type="submit" name="confirm_update" value="1" class="rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Atualizar o registro existente com estes dados
                </button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-shared.input name="title" label="Título" :value="$values['title'] ?? ''" required class="sm:col-span-2" />
        <x-shared.input name="subtitle" label="Subtítulo" :value="$values['subtitle'] ?? ''" class="sm:col-span-2" />
        <x-shared.input name="authors" label="Autores" :value="$values['authors'] ?? ''" hint="Separe vários autores com ponto e vírgula." class="sm:col-span-2" />
        <x-shared.input name="publisher" label="Editora" :value="$values['publisher'] ?? ''" />
        <x-shared.input name="isbn" label="ISBN (10 ou 13)" :value="$values['isbn'] ?? ''" inputmode="text" autocomplete="off"
                        hint="Opcional. Livros sem ISBN podem ser cadastrados normalmente." />
        <x-shared.input name="edition" label="Edição" :value="$values['edition'] ?? ''" />
        <x-shared.input name="publication_year" label="Ano de publicação" type="number" :value="$values['publication_year'] ?? ''" inputmode="numeric" />
        <x-shared.input name="pages" label="Páginas" type="number" :value="$values['pages'] ?? ''" inputmode="numeric" />
        <x-shared.input name="language" label="Idioma" :value="$values['language'] ?? ''" hint="Ex.: pt-BR, por, en" />
        <x-shared.input name="format" label="Formato" :value="$values['format'] ?? ''" hint="Ex.: Brochura, Livro digital" />
        <x-shared.input name="cover_url" label="URL da capa" type="url" :value="$values['cover_url'] ?? ''" class="sm:col-span-2" />

        <div class="sm:col-span-2">
            <label for="f-description" class="block text-sm font-medium text-slate-700">Descrição</label>
            <textarea id="f-description" name="description" rows="4"
                      class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600">{{ old('description', $values['description'] ?? '') }}</textarea>
            @error('description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-slate-700">Disciplina / categoria</legend>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-800 has-[:checked]:ring-indigo-400">
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" class="sr-only" @checked(in_array($category->id, $selectedCategories, true))>
                    {{ $category->name }}
                </label>
            @endforeach
        </div>
        <x-shared.input name="new_categories" label="Outras categorias" :value="''" class="mt-3"
                        hint="Crie categorias adicionais separando por vírgula.{{ $suggestedCategories ? ' Sugeridas pelas fontes: '.implode(', ', array_slice($suggestedCategories, 0, 5)).'.' : '' }}" />
    </fieldset>

    <div>
        <x-shared.input name="collection_name" label="Coleção didática" :value="$values['collection_name'] ?? ''" list="collections-list"
                        hint="Opcional. Ex.: nome da coleção do PNLD. A situação de verificação oficial é registrada à parte." />
        <datalist id="collections-list">
            @foreach ($collections as $collectionName)
                <option value="{{ $collectionName }}"></option>
            @endforeach
        </datalist>
    </div>

    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-6">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('books.index') }}" class="rounded-md px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Cancelar</a>
    </div>
</form>
