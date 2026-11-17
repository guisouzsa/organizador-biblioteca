@extends('layouts.app')

@section('title', 'Disciplinas e categorias — '.config('app.name'))

@php
    $cell = 'field px-2 py-1.5';
@endphp

@section('content')
    <x-shared.page-header title="Disciplinas e categorias" subtitle="Classificam os livros do catálogo. Itens em uso não são excluídos: são arquivados." />

    <form method="POST" action="{{ route('categories.store') }}" class="panel grid gap-3 p-4 sm:grid-cols-4" novalidate data-once data-inline-modal data-modal-title="Criar categoria ou disciplina" data-modal-trigger="Nova categoria">
        @csrf
        <p class="required-note sm:col-span-4"><span class="required-mark" aria-hidden="true">*</span> indica campo obrigatório.</p>
        <x-shared.input name="name" label="Nova categoria" :value="''" required class="sm:col-span-2" />
        <x-shared.select name="type" label="Tipo" :options="$types" value="discipline" required />
        <div class="form-actions flex items-end" data-form-actions><button class="btn btn-primary">Adicionar</button></div>
    </form>

    <div class="panel overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Livros</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($categories as $category)
                    @php($f = 'cat'.$category->id)
                    <tr class="{{ $category->archived_at ? 'bg-slate-50 text-slate-500' : '' }}">
                        <td class="px-4 py-2">
                            <form id="{{ $f }}" method="POST" action="{{ route('categories.update', $category) }}">@csrf @method('PUT')</form>
                            <input form="{{ $f }}" name="name" value="{{ $category->name }}" required aria-label="Nome" class="w-full {{ $cell }}">
                        </td>
                        <td class="px-4 py-2">
                            <select form="{{ $f }}" name="type" aria-label="Tipo" class="{{ $cell }}">
                                @foreach ($types as $value => $label)<option value="{{ $value }}" @selected($category->type->value === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </td>
                        <td class="px-4 py-2"><a href="{{ route('books.index', ['category' => $category->id]) }}" class="text-indigo-700 hover:underline">{{ $category->books_count }}</a></td>
                        <td class="px-4 py-2">@if ($category->archived_at)<x-shared.badge>Arquivada</x-shared.badge>@else<x-shared.badge tone="emerald">Ativa</x-shared.badge>@endif</td>
                        <td class="whitespace-nowrap px-4 py-2 text-right">
                            <button form="{{ $f }}" class="btn btn-secondary">Salvar</button>
                            <form method="POST" action="{{ route('categories.archive', $category) }}" class="inline" data-confirm="{{ $category->archived_at ? 'A categoria voltará a ficar disponível para novos cadastros.' : 'A categoria deixará de ficar disponível para novos cadastros. Os vínculos existentes serão preservados.' }}" data-confirm-title="{{ $category->archived_at ? 'Restaurar categoria?' : 'Arquivar categoria?' }}" data-confirm-label="{{ $category->archived_at ? 'Restaurar' : 'Arquivar' }}" @if(! $category->archived_at) data-confirm-danger @endif>@csrf
                                <button class="btn btn-secondary">{{ $category->archived_at ? 'Restaurar' : 'Arquivar' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mb-2 mt-10 text-lg">Coleções didáticas</h2>
    <p class="mb-4 text-sm text-slate-600">Ex.: coleções do PNLD. A situação “oficial” só deve ser marcada depois de conferir a fonte (FNDE, Seduc). O sistema não importa coleções automaticamente.</p>

    <form method="POST" action="{{ route('collections.store') }}" class="panel grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4" novalidate data-once data-inline-modal data-modal-title="Criar coleção didática" data-modal-trigger="Nova coleção">
        @csrf
        <p class="required-note sm:col-span-3 lg:col-span-4"><span class="required-mark" aria-hidden="true">*</span> indica campo obrigatório.</p>
        <x-shared.input name="name" label="Nome da coleção" :value="''" required class="sm:col-span-2" />
        <x-shared.input name="publisher" label="Editora" :value="''" />
        <x-shared.input name="fnde_code" label="Código FNDE" :value="''" />
        <x-shared.input name="program" label="Programa" :value="''" placeholder="Ex.: PNLD" />
        <x-shared.input name="cycle" label="Ciclo" :value="''" placeholder="Ex.: 2026–2029" />
        <x-shared.input name="source" label="Fonte da informação" :value="''" />
        <x-shared.select name="verification_status" label="Situação" :options="['unverified' => 'Não verificada', 'confirmed' => 'Conferida', 'official' => 'Oficial (fonte confirmada)']" value="unverified" />
        <div class="form-actions flex items-end" data-form-actions><button class="btn btn-primary">Adicionar coleção</button></div>
    </form>

    @if ($collections->isEmpty())
        <x-shared.empty-state title="Nenhuma coleção cadastrada." text="Coleções agrupam livros de um programa ou série didática." icon="tag" />
    @else
        <div class="panel overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Coleção</th><th class="px-4 py-3">Editora</th><th class="px-4 py-3">Programa / ciclo</th><th class="px-4 py-3">Livros</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($collections as $collection)
                        @php($f = 'col'.$collection->id)
                        <tr>
                            <td class="px-4 py-2"><form id="{{ $f }}" method="POST" action="{{ route('collections.update', $collection) }}">@csrf @method('PUT')</form>
                                <input form="{{ $f }}" name="name" value="{{ $collection->name }}" required aria-label="Nome" class="w-full {{ $cell }}"></td>
                            <td class="px-4 py-2"><input form="{{ $f }}" name="publisher" value="{{ $collection->publisher }}" aria-label="Editora" class="w-full {{ $cell }}"></td>
                            <td class="px-4 py-2">
                                <input form="{{ $f }}" name="program" value="{{ $collection->program }}" placeholder="Programa" aria-label="Programa" class="w-28 {{ $cell }}">
                                <input form="{{ $f }}" name="cycle" value="{{ $collection->cycle }}" placeholder="Ciclo" aria-label="Ciclo" class="w-28 {{ $cell }}">
                                <input form="{{ $f }}" type="hidden" name="fnde_code" value="{{ $collection->fnde_code }}">
                                <input form="{{ $f }}" type="hidden" name="source" value="{{ $collection->source }}">
                            </td>
                            <td class="px-4 py-2"><a href="{{ route('books.index', ['collection' => $collection->id]) }}" class="text-indigo-700 hover:underline">{{ $collection->books_count }}</a></td>
                            <td class="px-4 py-2">
                                <select form="{{ $f }}" name="verification_status" aria-label="Situação" class="{{ $cell }}">
                                    @foreach (['unverified' => 'Não verificada', 'confirmed' => 'Conferida', 'official' => 'Oficial'] as $v => $l)<option value="{{ $v }}" @selected($collection->verification_status === $v)>{{ $l }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2 text-right"><button form="{{ $f }}" class="btn btn-secondary">Salvar</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
