@extends('layouts.app')

@section('title', 'Balcão — '.config('app.name'))

@push('head')
    @vite('resources/js/camera-input.js')
@endpush

@php
    $input = 'field';
    $needsConfirmation = session('error_context.needs_confirmation');
@endphp

@section('content')
    <x-shared.page-header title="Balcão de circulação" subtitle="Identifique o leitor e leia o código do exemplar. Cada operação é gravada por completo ou não é gravada." />

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- EMPRÉSTIMO --}}
        <section class="panel min-w-0 p-4 sm:p-5" aria-labelledby="emprestar">
            <h2 id="emprestar" class="flex items-center gap-2 text-lg"><span class="grid h-7 w-7 place-items-center rounded-md bg-indigo-600 text-sm text-white">1</span> Emprestar</h2>

            @if (! $borrower)
                <form method="GET" action="{{ route('circulation.desk') }}" class="mt-4 space-y-2">
                    <label for="q" class="block text-sm font-medium text-slate-700">Leitor (matrícula ou nome)</label>
                    <div class="flex gap-2">
                        <input id="q" name="q" value="{{ $q }}" autofocus autocomplete="off" placeholder="Ex.: 202401 ou Maria" class="{{ $input }}">
                        <button class="btn btn-primary shrink-0">Buscar</button>
                    </div>
                </form>

                @if ($q !== '' && $candidates->isEmpty())
                    <div role="alert" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        Nenhum leitor encontrado para “{{ $q }}”. Confira a matrícula ou pesquise o nome.
                        @can('borrower.create')<a href="{{ route('borrowers.create') }}" class="font-medium underline">Cadastrar leitor</a>@endcan
                    </div>
                @endif

                @if ($candidates->isNotEmpty())
                    <ul class="mt-4 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                        @foreach ($candidates as $c)
                            <li><a href="{{ route('circulation.desk', ['leitor' => $c->id]) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                                <span><span class="font-medium">{{ $c->name }}</span> <span class="text-slate-500">· {{ $c->type->label() }}{{ $c->class_group ? ' · '.$c->class_group : '' }}{{ $c->registration_number ? ' · '.$c->registration_number : '' }}</span></span>
                                <x-shared.badge :tone="$c->isActive() ? 'emerald' : 'slate'">{{ $c->status->label() }}</x-shared.badge></a></li>
                        @endforeach
                    </ul>
                @endif
            @else
                <div class="mt-4 rounded-lg bg-slate-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold"><a href="{{ route('borrowers.show', $borrower) }}" class="hover:underline">{{ $borrower->name }}</a></p>
                            <p class="text-sm text-slate-600">{{ $borrower->type->label() }}{{ $borrower->class_group ? ' · '.$borrower->class_group : '' }}{{ $borrower->registration_number ? ' · matrícula '.$borrower->registration_number : '' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-shared.badge :tone="$borrower->isActive() ? 'emerald' : 'rose'">{{ $borrower->status->label() }}</x-shared.badge>
                            <a href="{{ route('circulation.desk') }}" class="text-sm font-medium text-indigo-700 hover:underline">Trocar leitor</a>
                        </div>
                    </div>

                    @if (! $borrower->isActive())
                        <p role="alert" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">Este leitor não está ativo e não pode pegar livros emprestados.</p>
                    @endif
                    @if (! $rule)
                        <p role="alert" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">As regras de empréstimo para “{{ $borrower->type->label() }}” ainda não foram configuradas. Peça a um administrador que as defina; enquanto isso, o empréstimo está bloqueado.</p>
                    @else
                        <p class="mt-3 text-xs text-slate-500">Prazo: {{ $rule->loan_days }} dia(s){{ $rule->max_active_loans ? ' · limite de '.$rule->max_active_loans.' empréstimo(s) simultâneo(s)' : '' }}.</p>
                    @endif

                    @if ($activeLoans->isNotEmpty())
                        <p class="mt-3 text-sm font-medium">Empréstimos ativos ({{ $activeLoans->count() }})</p>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($activeLoans as $loan)
                                <li class="flex items-center justify-between gap-2"><span class="truncate">{{ $loan->copy->book->title }}</span>
                                    @if ($loan->isOverdue())<x-shared.badge tone="rose">{{ $loan->daysOverdue() }} d de atraso</x-shared.badge>@else<span class="shrink-0 text-xs text-slate-500">até {{ $loan->due_on->format('d/m') }}</span>@endif</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <form method="POST" action="{{ route('loans.store') }}" class="mt-4 space-y-3" novalidate data-once>
                    @csrf
                    <input type="hidden" name="borrower_id" value="{{ $borrower->id }}">
                    @if ($needsConfirmation)<input type="hidden" name="acknowledge_overdue" value="1">@endif
                    <label for="copy_code" class="block text-sm font-medium text-slate-700">Código do exemplar (ou tombo)</label>
                    <div class="flex gap-2">
                        <input id="copy_code" name="copy_code" required autofocus autocomplete="off" placeholder="EX-000123" value="{{ old('copy_code') }}" data-autosubmit class="{{ $input }} font-mono uppercase">
                        <button type="button" data-camera-for="#copy_code" class="btn btn-secondary shrink-0">Câmera</button>
                    </div>
                    @error('copy_code')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="text-xs text-slate-500">Etiqueta ilegível? Digite o código impresso abaixo das barras ou use o tombo.</p>

                    @if ($needsConfirmation)
                        <p role="alert" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">Este leitor tem atraso. Ao enviar novamente você confirma o empréstimo mesmo assim.</p>
                    @endif
                    <button type="submit" @disabled(! $borrower->isActive() || ! $rule) class="btn btn-primary">
                        {{ $needsConfirmation ? 'Emprestar mesmo assim' : 'Registrar empréstimo' }}
                    </button>
                </form>
            @endif
        </section>

        {{-- DEVOLUÇÃO --}}
        <section class="panel min-w-0 p-4 sm:p-5" aria-labelledby="devolver">
            <h2 id="devolver" class="flex items-center gap-2 text-lg"><span class="grid h-7 w-7 place-items-center rounded-md bg-emerald-600 text-sm text-white">2</span> Devolver</h2>
            @can('loan.return')
                <form method="POST" action="{{ route('returns.store') }}" class="mt-4 space-y-3" novalidate data-once>
                    @csrf
                    @if ($borrower)<input type="hidden" name="hint_borrower_id" value="{{ $borrower->id }}">@endif
                    <label for="return_code" class="block text-sm font-medium text-slate-700">Código do exemplar (ou tombo)</label>
                    <div class="flex gap-2">
                        <input id="return_code" name="copy_code" required autocomplete="off" placeholder="EX-000123" data-autosubmit class="{{ $input }} font-mono uppercase">
                        <button type="button" data-camera-for="#return_code" class="btn btn-secondary shrink-0">Câmera</button>
                    </div>
                    <div>
                        <label for="condition" class="block text-sm font-medium text-slate-700">Conservação na devolução <span class="font-normal text-slate-500">(opcional)</span></label>
                        <select id="condition" name="condition" class="mt-1 {{ $input }}">
                            <option value="">Sem alteração</option>
                            @foreach ($conditions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Registrar devolução</button>
                </form>
            @endcan

            <h3 class="mt-8 text-sm font-semibold text-slate-700">Últimos empréstimos registrados</h3>
            <p class="text-xs text-slate-500">Confira aqui se um empréstimo foi gravado, caso a tela tenha falhado.</p>
            @forelse ($recent as $loan)
                <a href="{{ route('loans.show', $loan) }}" class="mt-2 flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-100 hover:bg-slate-50">
                    <span class="min-w-0"><span class="block truncate font-medium">{{ $loan->copy->book->title }}</span><span class="block truncate text-xs text-slate-500">{{ $loan->borrower->name }} · {{ $loan->copy->code() }} · {{ $loan->loaned_at->format('d/m H:i') }}</span></span>
                    @if ($loan->isActive())<x-shared.badge tone="amber">em andamento</x-shared.badge>@else<x-shared.badge tone="emerald">devolvido</x-shared.badge>@endif
                </a>
            @empty
                <div class="mt-3"><x-shared.empty-state title="Nenhum empréstimo ainda." text="Os empréstimos registrados aparecerão aqui." icon="swap" /></div>
            @endforelse
        </section>
    </div>
@endsection
