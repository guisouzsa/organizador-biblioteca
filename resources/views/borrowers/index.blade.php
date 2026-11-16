@extends('layouts.app')

@section('title', 'Leitores — '.config('app.name'))

@php
    $input = 'mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600';
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    $tone = ['active' => 'emerald', 'inactive' => 'slate', 'transferred' => 'amber', 'graduated' => 'sky'];
@endphp

@section('content')
    <x-shared.page-header title="Leitores" :subtitle="$borrowers->total().' '.($borrowers->total() === 1 ? 'leitor' : 'leitores').($hasFilters ? ' encontrados' : ' cadastrados')">
        @can('borrower.create')<a href="{{ route('borrowers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Cadastrar leitor</a>@endcan
    </x-shared.page-header>

    <form method="GET" action="{{ route('borrowers.index') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-5">
        <div class="sm:col-span-2">
            <label for="q" class="block text-sm font-medium text-slate-700">Buscar</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, matrícula ou turma" class="{{ $input }}">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-slate-700">Situação</label>
            <select id="status" name="status" class="{{ $input }}"><option value="">Todas</option>
                @foreach (\App\Enums\BorrowerStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-slate-700">Tipo</label>
            <select id="type" name="type" class="{{ $input }}"><option value="">Todos</option>
                @foreach (\App\Enums\BorrowerType::cases() as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-2 pb-2 text-sm text-slate-600"><input type="checkbox" name="atraso" value="1" @checked($filters['atraso'] ?? false) class="rounded border-slate-300 text-indigo-600"> Com atraso</label>
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
            <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            @if ($hasFilters)<a href="{{ route('borrowers.index') }}" class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Limpar filtros</a>@endif
        </div>
    </form>

    @if ($borrowers->isEmpty())
        <div class="mt-8">
            @if ($hasFilters)
                <x-shared.empty-state title="Nenhum leitor encontrado." text="Confira a matrícula ou tente outro termo." icon="users" />
            @else
                <x-shared.empty-state title="Nenhum leitor cadastrado." text="Cadastre os leitores (alunos e servidores) para registrar empréstimos." icon="users">
                    @can('borrower.create')<a href="{{ route('borrowers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Cadastrar leitor</a>@endcan
                </x-shared.empty-state>
            @endif
        </div>
    @else
        <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Matrícula</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Turma</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3">Empréstimos</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($borrowers as $b)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium"><a href="{{ route('borrowers.show', $b) }}" class="text-indigo-700 hover:underline">{{ $b->name }}</a></td>
                            <td class="px-4 py-3 font-mono">{{ $b->registration_number ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $b->type->label() }}</td>
                            <td class="px-4 py-3">{{ $b->class_group ?? '—' }}</td>
                            <td class="px-4 py-3"><x-shared.badge :tone="$tone[$b->status->value]">{{ $b->status->label() }}</x-shared.badge></td>
                            <td class="px-4 py-3">{{ $b->active_loans_count }} @if ($b->overdue_loans_count > 0)<x-shared.badge tone="rose">{{ $b->overdue_loans_count }} atrasado(s)</x-shared.badge>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $borrowers->links() }}</div>
    @endif
@endsection
