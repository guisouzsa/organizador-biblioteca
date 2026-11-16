@extends('layouts.app')

@section('title', 'Início — '.config('app.name'))

@section('content')
    <x-shared.page-header eyebrow="{{ now()->translatedFormat('l, d \d\e F') }}" title="Início" subtitle="Situação atual do acervo e da circulação, direto do banco de dados.">
        @can('loan.create')
            <a href="{{ route('circulation.desk') }}" class="btn btn-primary btn-lg flex-1 sm:flex-none"><x-shared.icon name="swap" class="h-5 w-5" /> Abrir balcão</a>
        @endcan
        @can('catalog.create')
            <a href="{{ route('books.scanner') }}" class="btn btn-secondary btn-lg flex-1 sm:flex-none"><x-shared.icon name="scan" class="h-5 w-5" /> Escanear livro</a>
        @endcan
    </x-shared.page-header>

    @if (! $rulesConfigured || $typesWithoutRule)
        <x-ui.alert tone="warning" title="Empréstimos bloqueados até a configuração das regras" class="mb-8">
            @if (! $rulesConfigured)
                Nenhuma regra de empréstimo foi definida. O sistema não inventa prazos nem limites: a escola precisa configurá-los.
            @else
                Faltam regras para: {{ collect($typesWithoutRule)->map->label()->implode(', ') }}.
            @endif
            @can('settings.manage')
                <a href="{{ route('loan-rules.edit') }}" class="font-semibold underline">Configurar agora</a>.
            @else
                Peça a um administrador que as configure.
            @endcan
        </x-ui.alert>
    @endif

    {{-- Três números que orientam o dia; o restante fica em segundo plano --}}
    <section aria-label="Resumo da circulação" class="grid grid-cols-3 gap-x-4 gap-y-6">
        <x-shared.stat-card label="Disponíveis" :value="$stats['copies_available']" :href="route('copies.index', ['situacao' => 'available'])" :hint="'de '.$stats['copies'].' exemplares'" />
        <x-shared.stat-card label="Emprestados" :value="$stats['copies_on_loan']" :href="route('copies.index', ['situacao' => 'on_loan'])" :hint="$dueToday.' vencem hoje'" />
        <x-shared.stat-card label="Atrasados" tone="rose" :value="$stats['loans_overdue']" :href="route('loans.index', ['situacao' => 'overdue'])" hint="empréstimos" />
    </section>

    <section aria-label="Acervo e pessoas" class="mt-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-4">
        <x-shared.stat-card label="Livros" :value="$stats['books']" :href="route('books.index')" />
        <x-shared.stat-card label="Exemplares" :value="$stats['copies']" :href="route('copies.index')" :hint="$stats['copies_unavailable'].' fora de circulação'" />
        <x-shared.stat-card label="Leitores ativos" :value="$stats['borrowers_active']" :href="route('borrowers.index')" />
        <x-shared.stat-card label="Reservas" :value="$stats['reservations_pending']" :href="route('reservations.index')" :hint="$stats['reservations_ready'].' prontas p/ retirada'" />
    </section>

    <hr class="divisor-patas mt-10" aria-hidden="true">

    <div class="grid gap-10 lg:grid-cols-2">
        <section aria-labelledby="atrasos">
            <div class="flex items-baseline justify-between border-b border-slate-300 pb-2">
                <h2 id="atrasos" class="text-xl">Em atraso</h2>
                <a href="{{ route('loans.index', ['situacao' => 'overdue']) }}" class="link-ondulado text-sm font-medium text-indigo-700">Ver todos</a>
            </div>
            <ul>
                @forelse ($overdue as $loan)
                    <li class="border-b border-slate-200">
                        <a href="{{ route('loans.show', $loan) }}" class="flex min-h-14 items-center justify-between gap-3 py-2.5 hover:bg-slate-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium">{{ $loan->copy->book->title }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $loan->borrower->name }} · <span class="catalog-id">{{ $loan->copy->code() }}</span></span>
                            </span>
                            <x-shared.badge tone="rose">{{ $loan->daysOverdue() }} {{ $loan->daysOverdue() === 1 ? 'dia' : 'dias' }}</x-shared.badge>
                        </a>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500">Nenhum empréstimo em atraso.</li>
                @endforelse
            </ul>
        </section>

        <section aria-labelledby="prontas">
            <div class="flex items-baseline justify-between border-b border-slate-300 pb-2">
                <h2 id="prontas" class="text-xl">Prontas para retirada</h2>
                <a href="{{ route('reservations.index') }}" class="link-ondulado text-sm font-medium text-indigo-700">Ver reservas</a>
            </div>
            <ul>
                @forelse ($ready as $reservation)
                    <li class="flex min-h-14 items-center justify-between gap-3 border-b border-slate-200 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{ $reservation->book->title }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $reservation->borrower->name }} · <span class="catalog-id">{{ $reservation->copy?->code() }}</span></span>
                        </span>
                        @if ($reservation->expires_at)<x-shared.badge tone="sky">até {{ $reservation->expires_at->format('d/m H:i') }}</x-shared.badge>@endif
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500">Nenhuma reserva aguardando retirada.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if ($stats['books'] === 0)
        <div class="mt-10">
            <x-shared.empty-state title="A biblioteca ainda está vazia" text="Comece cadastrando livros no catálogo; depois registre os exemplares físicos e os leitores." icon="book" gato="dormindo">
                @can('catalog.create')<a href="{{ route('books.scanner') }}" class="btn btn-primary">Escanear primeiro livro</a>@endcan
            </x-shared.empty-state>
        </div>
    @endif
    <div class="mt-12 flex justify-end" aria-hidden="true">
        <x-shared.gato nome="lapis" class="balanca h-16 w-auto opacity-80" />
    </div>
@endsection
