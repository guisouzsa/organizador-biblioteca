@extends('layouts.app')

@section('title', $borrower->name.' — '.config('app.name'))

@php($tone = ['active' => 'emerald', 'inactive' => 'slate', 'transferred' => 'amber', 'graduated' => 'sky'])

@section('content')
    <nav class="mb-4 text-sm"><a href="{{ route('borrowers.index') }}" class="back-link">← Voltar aos leitores</a></nav>

    <x-shared.page-header :title="$borrower->name" :subtitle="$borrower->type->label().($borrower->registration_number ? ' · matrícula '.$borrower->registration_number : '').($borrower->class_group ? ' · '.$borrower->class_group : '')">
        <x-shared.badge :tone="$tone[$borrower->status->value]" class="self-center">{{ $borrower->status->label() }}</x-shared.badge>
        @can('loan.create')@if ($borrower->isActive())<a href="{{ route('circulation.desk', ['leitor' => $borrower->id]) }}" class="btn btn-primary">Registrar empréstimo</a>@endif @endcan
        @can('update', $borrower)<a href="{{ route('borrowers.edit', $borrower) }}" class="btn btn-secondary">Editar</a>@endcan
    </x-shared.page-header>

    @if ($borrower->anonymized_at)
        <div class="mb-6 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">Dados pessoais removidos em {{ $borrower->anonymized_at->format('d/m/Y') }}. O histórico de empréstimos foi mantido de forma anônima.</div>
    @elseif (! $borrower->isActive())
        <div role="alert" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Este leitor está <strong>{{ mb_strtolower($borrower->status->label()) }}</strong> e não pode pegar livros emprestados. O histórico foi preservado.</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="panel p-5" aria-labelledby="ativos">
                <h2 id="ativos" class="font-semibold">Empréstimos ativos ({{ $activeLoans->count() }})</h2>
                @forelse ($activeLoans as $loan)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-100">
                        <a href="{{ route('loans.show', $loan) }}" class="min-w-0"><span class="block truncate font-medium">{{ $loan->copy->book->title }}</span>
                            <span class="text-xs text-slate-500">{{ $loan->copy->code() }} · desde {{ $loan->loaned_at->format('d/m/Y') }}</span></a>
                        <span>@if ($loan->isOverdue())<x-shared.badge tone="rose">{{ $loan->daysOverdue() }} dia(s) de atraso</x-shared.badge>@else<x-shared.badge tone="amber">devolver até {{ $loan->due_on->format('d/m') }}</x-shared.badge>@endif</span>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-500">Nenhum empréstimo ativo.</p>
                @endforelse
            </section>

            <section class="panel p-5" aria-labelledby="historico">
                <h2 id="historico" class="font-semibold">Histórico de empréstimos</h2>
                @forelse ($history as $loan)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="min-w-0"><span class="block truncate">{{ $loan->copy->book->title }}</span><span class="text-xs text-slate-500">{{ $loan->copy->code() }} · {{ $loan->loaned_at->format('d/m/Y') }} → {{ $loan->returned_at->format('d/m/Y') }}</span></span>
                        @if ($loan->outcome) <x-shared.badge :tone="$loan->outcome->value === 'returned' ? 'emerald' : 'rose'">{{ $loan->outcome->label() }}</x-shared.badge>@endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-500">Sem empréstimos anteriores.</p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="panel p-5">
                <h2 class="font-semibold">Dados</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">Cadastrado em</dt><dd>{{ $borrower->created_at->format('d/m/Y') }}</dd></div>
                    @can('viewContacts', $borrower)
                        <div><dt class="text-slate-500">Telefone</dt><dd>{{ $borrower->phone ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">E-mail</dt><dd class="break-all">{{ $borrower->email ?? '—' }}</dd></div>
                    @endcan
                </dl>
            </section>

            <section class="panel p-5">
                <h2 class="font-semibold">Reservas ativas</h2>
                @forelse ($reservations as $reservation)
                    <p class="mt-2 text-sm"><a href="{{ route('reservations.index', ['livro' => $reservation->book_id]) }}" class="hover:underline">{{ $reservation->book->title }}</a> <x-shared.badge tone="sky">{{ $reservation->status->label() }}</x-shared.badge></p>
                @empty
                    <p class="mt-2 text-sm text-slate-500">Nenhuma reserva.</p>
                @endforelse
            </section>

            @can('changeStatus', $borrower)
                <section class="panel p-5">
                    <h2 class="font-semibold">Situação do leitor</h2>
                    <form method="POST" action="{{ route('borrowers.status', $borrower) }}" class="mt-3 space-y-3" data-once data-confirm="A situação de {{ $borrower->name }} será alterada. O histórico de empréstimos é preservado e as reservas ativas podem ser canceladas." data-confirm-title="Alterar a situação do leitor?" data-confirm-label="Alterar situação">
                        @csrf
                        <select name="status" aria-label="Nova situação" class="field">
                            @foreach (\App\Enums\BorrowerStatus::cases() as $s)<option value="{{ $s->value }}" @selected($borrower->status === $s)>{{ $s->label() }}</option>@endforeach
                        </select>
                        <x-shared.input name="reason" label="Motivo (opcional)" :value="''" />
                        <button class="btn btn-primary">Aplicar</button>
                        <p class="text-xs text-slate-500">Inativar cancela as reservas do leitor. Leitores com empréstimos ativos só mudam de situação após as devoluções.</p>
                    </form>
                </section>
            @endcan

            @can('anonymize', $borrower)
                <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-rose-200">
                    <h2 class="font-semibold text-rose-800">Remover dados pessoais (LGPD)</h2>
                    <p class="mt-1 text-xs text-slate-600">Apaga nome, matrícula e contatos, mantendo o histórico de forma anônima. <strong>Irreversível.</strong></p>
                    <form method="POST" action="{{ route('borrowers.anonymize', $borrower) }}" class="mt-3" data-once data-confirm="Os dados pessoais de {{ $borrower->name }} serão removidos definitivamente. O histórico de empréstimos continua, sem identificar a pessoa." data-confirm-title="Remover dados pessoais?" data-confirm-label="Remover dados" data-confirm-danger">
                        @csrf<button class="btn btn-danger">Anonimizar leitor</button>
                    </form>
                </section>
            @endcan
        </div>
    </div>
@endsection
