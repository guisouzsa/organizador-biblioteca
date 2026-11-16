@extends('layouts.app')

@section('title', 'Painel — '.config('app.name'))

@section('content')
    <x-shared.page-header title="Painel" subtitle="Situação atual da biblioteca. Os números vêm do banco de dados em tempo real.">
        @can('loan.create')
            <a href="{{ route('circulation.desk') }}" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Abrir balcão</a>
        @endcan
        @can('catalog.create')
            <a href="{{ route('books.scanner') }}" class="rounded-md px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Escanear livro</a>
        @endcan
    </x-shared.page-header>

    @if (! $rulesConfigured || $typesWithoutRule)
        <div role="alert" class="mb-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-shared.icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-medium">Empréstimos bloqueados até a configuração das regras</p>
                <p class="mt-0.5">
                    @if (! $rulesConfigured)
                        Nenhuma regra de empréstimo foi definida. O sistema não inventa prazos nem limites: a escola precisa configurá-los.
                    @else
                        Faltam regras para: {{ collect($typesWithoutRule)->map->label()->implode(', ') }}.
                    @endif
                    @can('settings.manage')
                        <a href="{{ route('loan-rules.edit') }}" class="font-medium underline">Configurar agora</a>.
                    @else
                        Peça a um administrador que as configure.
                    @endcan
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-shared.stat-card label="Livros no catálogo" :value="$stats['books']" icon="book" :href="route('books.index')" />
        <x-shared.stat-card label="Exemplares" :value="$stats['copies']" icon="stack" tone="sky" :href="route('copies.index')" :hint="$stats['copies_unavailable'].' fora de circulação'" />
        <x-shared.stat-card label="Exemplares disponíveis" :value="$stats['copies_available']" icon="check" tone="emerald" :href="route('copies.index', ['situacao' => 'available'])" />
        <x-shared.stat-card label="Exemplares emprestados" :value="$stats['copies_on_loan']" icon="swap" tone="amber" :href="route('copies.index', ['situacao' => 'on_loan'])" />
        <x-shared.stat-card label="Leitores ativos" :value="$stats['borrowers_active']" icon="users" :href="route('borrowers.index')" />
        <x-shared.stat-card label="Empréstimos em andamento" :value="$stats['loans_active']" icon="list" :href="route('loans.index', ['situacao' => 'active'])" :hint="$dueToday.' vencem hoje'" />
        <x-shared.stat-card label="Empréstimos atrasados" :value="$stats['loans_overdue']" icon="alert" tone="rose" :href="route('loans.index', ['situacao' => 'overdue'])" />
        <x-shared.stat-card label="Reservas pendentes" :value="$stats['reservations_pending']" icon="bookmark" tone="sky" :href="route('reservations.index')" :hint="$stats['reservations_ready'].' prontas para retirada'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200" aria-labelledby="atrasos">
            <h2 id="atrasos" class="font-semibold">Empréstimos em atraso</h2>
            @forelse ($overdue as $loan)
                <a href="{{ route('loans.show', $loan) }}" class="mt-3 flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-100 hover:bg-slate-50">
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $loan->copy->book->title }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $loan->borrower->name }} · {{ $loan->copy->code() }}</span>
                    </span>
                    <x-shared.badge tone="rose">{{ $loan->daysOverdue() }} {{ $loan->daysOverdue() === 1 ? 'dia' : 'dias' }}</x-shared.badge>
                </a>
            @empty
                <p class="mt-3 text-sm text-slate-500">Nenhum empréstimo em atraso.</p>
            @endforelse
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200" aria-labelledby="prontas">
            <h2 id="prontas" class="font-semibold">Reservas prontas para retirada</h2>
            @forelse ($ready as $reservation)
                <div class="mt-3 flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-100">
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $reservation->book->title }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $reservation->borrower->name }} · {{ $reservation->copy?->code() }}</span>
                    </span>
                    @if ($reservation->expires_at)<x-shared.badge tone="sky">até {{ $reservation->expires_at->format('d/m H:i') }}</x-shared.badge>@endif
                </div>
            @empty
                <p class="mt-3 text-sm text-slate-500">Nenhuma reserva aguardando retirada.</p>
            @endforelse
        </section>
    </div>

    @if ($stats['books'] === 0)
        <div class="mt-8">
            <x-shared.empty-state title="A biblioteca ainda está vazia" text="Comece cadastrando livros no catálogo, depois registre os exemplares físicos e os leitores." icon="book">
                @can('catalog.create')<a href="{{ route('books.scanner') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Escanear primeiro livro</a>@endcan
            </x-shared.empty-state>
        </div>
    @endif
@endsection
