@extends('layouts.app')

@section('title', 'Empréstimos — '.config('app.name'))

@php
    $input = 'field';
    $tabs = ['active' => 'Em andamento', 'overdue' => 'Atrasados', 'returned' => 'Devolvidos', 'all' => 'Todos'];
@endphp

@section('content')
    <x-shared.page-header title="Empréstimos" :subtitle="$loans->total().' '.($loans->total() === 1 ? 'registro' : 'registros')">
        @can('loan.create')<a href="{{ route('circulation.desk') }}" class="btn btn-primary">Abrir balcão</a>@endcan
    </x-shared.page-header>

    <div class="mb-4 flex flex-wrap gap-1" role="tablist" aria-label="Situação">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('loans.index', array_merge(request()->except('page'), ['situacao' => $key])) }}" role="tab" aria-selected="{{ $situation === $key ? 'true' : 'false' }}"
               class="rounded-md px-4 py-1.5 text-sm font-medium {{ $situation === $key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('loans.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="situacao" value="{{ $situation }}">
        <div class="sm:col-span-2"><label for="q" class="block text-sm font-medium text-slate-700">Buscar</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Leitor, matrícula, livro ou código" class="{{ $input }}"></div>
        <div><label for="de" class="block text-sm font-medium text-slate-700">Emprestado de</label><input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}" class="{{ $input }}"></div>
        <div><label for="ate" class="block text-sm font-medium text-slate-700">até</label><input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}" class="{{ $input }}"></div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-4"><button class="btn btn-primary">Filtrar</button>
            <a href="{{ route('loans.index', ['situacao' => $situation]) }}" class="btn btn-secondary">Limpar</a></div>
    </form>

    @if ($loans->isEmpty())
        <div class="mt-8"><x-shared.empty-state :title="$situation === 'overdue' ? 'Nenhum empréstimo em atraso.' : 'Nenhum empréstimo encontrado.'" text="Os empréstimos registrados no balcão aparecem aqui." icon="list" /></div>
    @else
        <div class="panel mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Livro</th><th class="px-4 py-3">Exemplar</th><th class="px-4 py-3">Leitor</th><th class="px-4 py-3">Emprestado</th><th class="px-4 py-3">Devolução</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($loans as $loan)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium"><a href="{{ route('loans.show', $loan) }}" class="hover:underline">{{ $loan->copy->book->title }}</a></td>
                            <td class="px-4 py-3 font-mono">{{ $loan->copy->code() }}</td>
                            <td class="px-4 py-3"><a href="{{ route('borrowers.show', $loan->borrower) }}" class="hover:underline">{{ $loan->borrower->name }}</a></td>
                            <td class="px-4 py-3">{{ $loan->loaned_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $loan->isActive() ? 'prevista '.$loan->due_on->format('d/m/Y') : $loan->returned_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($loan->outcome === \App\Enums\LoanOutcome::Voided)<x-shared.badge>Estornado</x-shared.badge>
                                @elseif ($loan->outcome === \App\Enums\LoanOutcome::Lost)<x-shared.badge tone="rose">Perdido</x-shared.badge>
                                @elseif ($loan->isOverdue())<x-shared.badge tone="rose">{{ $loan->daysOverdue() }} dia(s) de atraso</x-shared.badge>
                                @elseif ($loan->isActive())<x-shared.badge tone="amber">Em andamento</x-shared.badge>
                                @else<x-shared.badge tone="emerald">Devolvido</x-shared.badge>@endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('renew', $loan)
                                    <form method="POST" action="{{ route('loans.renew', $loan) }}" class="inline" data-once>@csrf<button class="btn btn-secondary">Renovar</button></form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $loans->links() }}</div>
    @endif
@endsection
