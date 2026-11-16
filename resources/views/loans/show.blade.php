@extends('layouts.app')

@section('title', 'Empréstimo #'.$loan->id.' — '.config('app.name'))

@section('content')
    <nav class="mb-4 text-sm"><a href="{{ route('loans.index') }}" class="inline-block py-2 text-indigo-700 hover:underline">← Voltar aos empréstimos</a></nav>

    <x-shared.page-header :title="'Empréstimo #'.$loan->id" :subtitle="$loan->copy->book->title">
        @if ($loan->outcome === \App\Enums\LoanOutcome::Voided)<x-shared.badge class="self-center">Estornado</x-shared.badge>
        @elseif ($loan->outcome === \App\Enums\LoanOutcome::Lost)<x-shared.badge tone="rose" class="self-center">Encerrado como perdido</x-shared.badge>
        @elseif ($loan->isOverdue())<x-shared.badge tone="rose" class="self-center">{{ $loan->daysOverdue() }} dia(s) de atraso</x-shared.badge>
        @elseif ($loan->isActive())<x-shared.badge tone="amber" class="self-center">Em andamento</x-shared.badge>
        @else<x-shared.badge tone="emerald" class="self-center">Devolvido</x-shared.badge>@endif
        @can('renew', $loan)<form method="POST" action="{{ route('loans.renew', $loan) }}" data-once>@csrf<button class="btn btn-primary">Renovar</button></form>@endcan
    </x-shared.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="panel p-5 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Leitor</dt><dd class="font-medium"><a href="{{ route('borrowers.show', $loan->borrower) }}" class="text-indigo-700 hover:underline">{{ $loan->borrower->name }}</a></dd></div>
                <div><dt class="text-slate-500">Exemplar</dt><dd class="font-mono font-medium"><a href="{{ route('copies.show', $loan->copy) }}" class="text-indigo-700 hover:underline">{{ $loan->copy->code() }}</a></dd></div>
                <div><dt class="text-slate-500">Emprestado em</dt><dd>{{ $loan->loaned_at->format('d/m/Y H:i') }} por {{ $loan->loanedBy->name }}</dd></div>
                <div><dt class="text-slate-500">Devolução prevista</dt><dd class="font-medium">{{ $loan->due_on->format('d/m/Y') }}</dd></div>
                @if ($loan->returned_at)<div><dt class="text-slate-500">Encerrado em</dt><dd>{{ $loan->returned_at->format('d/m/Y H:i') }}{{ $loan->returnedBy ? ' por '.$loan->returnedBy->name : '' }}</dd></div>@endif
                @if ($loan->notes)<div class="sm:col-span-2"><dt class="text-slate-500">Observações</dt><dd>{{ $loan->notes }}</dd></div>@endif
            </dl>
        </section>

        <section class="panel p-5">
            <h2 class="font-semibold">Renovações ({{ $loan->renewals->count() }})</h2>
            @forelse ($loan->renewals as $renewal)
                <p class="mt-2 text-sm">{{ $renewal->created_at->format('d/m/Y') }}: {{ $renewal->previous_due_on->format('d/m') }} → <strong>{{ $renewal->new_due_on->format('d/m/Y') }}</strong><span class="text-xs text-slate-500"> · {{ $renewal->renewedBy->name }}</span></p>
            @empty
                <p class="mt-2 text-sm text-slate-500">Nenhuma renovação.</p>
            @endforelse
        </section>
    </div>

    @can('void', $loan)
        <section class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-rose-200">
            <h2 class="font-semibold text-rose-800">Estornar empréstimo</h2>
            <p class="mt-1 text-sm text-slate-600">Para corrigir um lançamento feito por engano. O registro <strong>não é apagado</strong>: fica marcado como estornado, com motivo e autoria.</p>
            <form method="POST" action="{{ route('loans.void', $loan) }}" class="mt-3 flex flex-wrap items-end gap-3" data-once onsubmit="return confirm('Estornar este empréstimo?')">
                @csrf
                <x-shared.input name="reason" label="Motivo do estorno" :value="''" required class="min-w-64 flex-1" />
                <button class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">Estornar</button>
            </form>
        </section>
    @endcan
@endsection
