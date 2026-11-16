@extends('layouts.app')

@section('title', 'Acervo — '.config('app.name'))

@push('head')
    @vite('resources/js/camera-input.js')
@endpush

@php
    $input = 'mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600';
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    $situations = ['available' => 'Disponível', 'on_loan' => 'Emprestado', 'held' => 'Retido para reserva', 'maintenance' => 'Em manutenção', 'lost' => 'Perdido', 'written_off' => 'Baixado', 'no_location' => 'Sem localização'];
@endphp

@section('content')
    <x-shared.page-header title="Acervo (exemplares)" :subtitle="$copies->total().' '.($copies->total() === 1 ? 'exemplar' : 'exemplares').($hasFilters ? ' encontrados' : ' registrados')">
        @can('copy.create')
            <a href="{{ route('books.index') }}" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Registrar exemplares (escolha o livro)</a>
        @endcan
    </x-shared.page-header>

    @if ($book)
        <p class="mb-4 text-sm text-slate-600">Exemplares de <a href="{{ route('books.show', $book) }}" class="font-medium text-indigo-700 hover:underline">{{ $book->title }}</a>.</p>
    @endif

    <form method="GET" action="{{ route('copies.index') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
        @if ($book)<input type="hidden" name="livro" value="{{ $book->id }}">@endif
        <div class="sm:col-span-2">
            <label for="q" class="block text-sm font-medium text-slate-700">Código, tombo ou livro</label>
            <div class="mt-1 flex gap-2">
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="EX-000123, tombo, título ou autor" autocomplete="off" class="block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" data-autosubmit>
                <button type="button" data-camera-for="#q" class="shrink-0 rounded-md px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Câmera</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Um código ou tombo exato abre direto a ficha do exemplar.</p>
        </div>
        <div>
            <label for="situacao" class="block text-sm font-medium text-slate-700">Situação</label>
            <select id="situacao" name="situacao" class="{{ $input }}">
                <option value="">Todas</option>
                @foreach ($situations as $key => $label)<option value="{{ $key }}" @selected(($filters['situacao'] ?? '') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="local" class="block text-sm font-medium text-slate-700">Localização</label>
            <select id="local" name="local" class="{{ $input }}">
                <option value="">Todas</option>
                @foreach ($locations as $location)<option value="{{ $location->id }}" @selected(($filters['local'] ?? null) == $location->id)>{{ $location->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            @if ($hasFilters)<a href="{{ route('copies.index') }}" class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Limpar filtros</a>@endif
        </div>
    </form>

    @if ($copies->isEmpty())
        <div class="mt-8">
            @if ($hasFilters)
                <x-shared.empty-state title="Nenhum exemplar corresponde à busca." text="Confira o código ou tente outros filtros. Código danificado? Busque pelo título do livro." icon="stack" />
            @else
                <x-shared.empty-state title="Ainda não há exemplares registrados." text="Abra um livro do catálogo e use “Registrar exemplares”. Cada cópia física recebe um código de barras próprio." icon="stack">
                    @can('catalog.view')<a href="{{ route('books.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Ir ao catálogo</a>@endcan
                </x-shared.empty-state>
            @endif
        </div>
    @else
        <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Livro</th><th class="px-4 py-3">Localização</th><th class="px-4 py-3">Conservação</th><th class="px-4 py-3">Situação</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($copies as $copy)
                        @php($s = $copy->situation())
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono"><a href="{{ route('copies.show', $copy) }}" class="font-medium text-indigo-700 hover:underline">{{ $copy->code() }}</a></td>
                            <td class="px-4 py-3"><a href="{{ route('books.show', $copy->book) }}" class="hover:underline">{{ $copy->book->title }}</a></td>
                            <td class="px-4 py-3">{{ $copy->location?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $copy->condition->label() }}</td>
                            <td class="px-4 py-3"><x-shared.badge :tone="$s['tone']">{{ $s['label'] }}</x-shared.badge>@if ($copy->activeLoan)<span class="ml-1 text-xs text-slate-500">até {{ $copy->activeLoan->due_on->format('d/m') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $copies->links() }}</div>
    @endif
@endsection
