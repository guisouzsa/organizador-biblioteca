@extends('layouts.app')

@section('title', ($copy->code() ?? 'Exemplar').' — '.config('app.name'))

@section('content')
    <nav class="mb-4 text-sm"><a href="{{ route('copies.index') }}" class="back-link">← Voltar ao acervo</a></nav>

    <x-shared.page-header :title="$copy->code() ?? 'Exemplar'" :subtitle="$copy->book->title">
        <x-shared.badge :tone="$situation['tone']" class="self-center">{{ $situation['label'] }}</x-shared.badge>
        <a href="{{ route('copies.labels', ['ids' => [$copy->id]]) }}" class="btn btn-secondary">Imprimir etiqueta</a>
        <a href="{{ route('books.show', $copy->book) }}" class="btn btn-secondary">Ver livro</a>
    </x-shared.page-header>

    @if ($copy->status !== \App\Enums\CopyStatus::Active)
        <div role="alert" class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Este exemplar está <strong>{{ mb_strtolower($copy->status->label()) }}</strong> e não circula.@if ($copy->status_reason) Motivo: {{ $copy->status_reason }}.@endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="panel p-5">
                <h2 class="font-semibold">Situação atual</h2>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Localização</dt><dd class="font-medium">{{ $copy->location?->fullName() ?? 'Sem localização' }}</dd></div>
                    <div><dt class="text-slate-500">Conservação</dt><dd class="font-medium">{{ $copy->condition->label() }}</dd></div>
                    <div><dt class="text-slate-500">Tombo</dt><dd class="font-medium">{{ $copy->accession_number ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Aquisição</dt><dd class="font-medium">{{ collect([$copy->acquisition_source, $copy->acquired_at?->format('d/m/Y')])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    @if ($copy->activeLoan)
                        <div class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-amber-900">
                            Emprestado a <a href="{{ route('borrowers.show', $copy->activeLoan->borrower) }}" class="font-medium underline">{{ $copy->activeLoan->borrower->name }}</a>,
                            devolução prevista em <strong>{{ $copy->activeLoan->due_on->format('d/m/Y') }}</strong>
                            @if ($copy->activeLoan->isOverdue())<x-shared.badge tone="rose">{{ $copy->activeLoan->daysOverdue() }} dia(s) de atraso</x-shared.badge>@endif
                            · <a href="{{ route('loans.show', $copy->activeLoan) }}" class="underline">ver empréstimo</a>
                        </div>
                    @elseif ($copy->heldReservation)
                        <div class="sm:col-span-2 rounded-lg bg-sky-50 p-3 text-sky-900">Retido para <strong>{{ $copy->heldReservation->borrower->name }}</strong>@if ($copy->heldReservation->expires_at) até {{ $copy->heldReservation->expires_at->format('d/m H:i') }}@endif.</div>
                    @endif
                    @if ($copy->notes)<div class="sm:col-span-2"><dt class="text-slate-500">Observações</dt><dd class="whitespace-pre-line">{{ $copy->notes }}</dd></div>@endif
                </dl>
            </section>

            @can('update', $copy)
                <section class="panel p-5">
                    <h2 class="font-semibold">Editar dados do exemplar</h2>
                    <form method="POST" action="{{ route('copies.update', $copy) }}" class="mt-4 space-y-4" novalidate data-once>
                        @csrf @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-shared.select name="location_id" label="Localização" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->fullName()])->all()" :value="$copy->location_id" placeholder="Sem localização" />
                            <x-shared.select name="condition" label="Conservação" :options="$conditions" :value="$copy->condition->value" required />
                            <x-shared.input name="accession_number" label="Tombo" :value="$copy->accession_number" />
                            <x-shared.input name="acquisition_source" label="Origem" :value="$copy->acquisition_source" />
                            <x-shared.input name="acquired_at" label="Data de aquisição" type="date" :value="$copy->acquired_at?->format('Y-m-d')" />
                            <x-shared.textarea name="notes" label="Observações" :value="$copy->notes" class="sm:col-span-2" />
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </form>
                </section>
            @endcan

            <section class="panel p-5" aria-labelledby="historico">
                <h2 id="historico" class="font-semibold">Histórico de movimentações</h2>
                @forelse ($events as $event)
                    <div class="mt-3 flex gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-md bg-indigo-400"></span>
                        <div>
                            <p><span class="font-medium">{{ $event->type->label() }}</span>
                                @if ($event->loan?->borrower)<span class="text-slate-600"> — {{ $event->loan->borrower->name }}</span>@endif</p>
                            <p class="text-xs text-slate-500">{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->user?->name ?? 'Sistema' }}
                                @if ($event->payload) · {{ collect($event->payload)->map(fn ($v, $k) => $k.': '.(is_array($v) ? json_encode($v) : $v))->implode(', ') }}@endif</p>
                        </div>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-500">Sem movimentações.</p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="panel p-5">
                <h2 class="font-semibold">Códigos de barras</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($copy->barcodes->sortByDesc('active') as $barcode)
                        <li class="flex items-center justify-between gap-2">
                            <span class="font-mono">{{ $barcode->code }}</span>
                            @if ($barcode->active)<x-shared.badge tone="emerald">Atual</x-shared.badge>@else<x-shared.badge tone="slate">Antigo</x-shared.badge>@endif
                        </li>
                    @endforeach
                </ul>
                @can('create', \App\Models\BookCopy::class)
                    <form method="POST" action="{{ route('copies.relabel', $copy) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4" data-once>
                        @csrf
                        <label for="reason" class="block text-xs font-medium text-slate-600">Etiqueta danificada? Gere uma nova</label>
                        <input id="reason" name="reason" required maxlength="120" placeholder="Motivo (ex.: etiqueta rasgada)" class="field">
                        @error('reason')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        <button class="btn btn-secondary">Gerar nova etiqueta</button>
                        <p class="text-xs text-slate-500">A etiqueta antiga continua identificando este exemplar e o histórico é mantido.</p>
                    </form>
                @endcan
            </section>

            @if ($statuses->isNotEmpty() && (auth()->user()->can('transition', $copy) || ($copy->status === \App\Enums\CopyStatus::WrittenOff && auth()->user()->can('restore', $copy))))
                <section class="panel p-5">
                    <h2 class="font-semibold">Alterar situação</h2>
                    <form method="POST" action="{{ route('copies.transition', $copy) }}" class="mt-3 space-y-3" data-once
                          data-confirm="A situação do exemplar {{ $copy->code() }} será alterada e ficará registrada no histórico." data-confirm-title="Alterar a situação do exemplar?" data-confirm-label="Alterar situação">
                        @csrf
                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-700">Nova situação</label>
                            <select id="status" name="status" class="field">
                                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                            </select>
                        </div>
                        <x-shared.input name="reason" label="Motivo" :value="''" hint="Obrigatório para perdido e baixa." />
                        <button class="btn btn-primary">Aplicar</button>
                    </form>
                </section>
            @endif

            <section class="panel p-5">
                <h2 class="font-semibold">Últimos empréstimos</h2>
                @forelse ($loans as $loan)
                    <a href="{{ route('loans.show', $loan) }}" class="mt-2 block rounded-lg px-3 py-2 text-sm ring-1 ring-slate-100 hover:bg-slate-50">
                        <span class="block font-medium">{{ $loan->borrower->name }}</span>
                        <span class="text-xs text-slate-500">{{ $loan->loaned_at->format('d/m/Y') }} → {{ $loan->returned_at?->format('d/m/Y') ?? 'em andamento' }}</span>
                    </a>
                @empty
                    <p class="mt-2 text-sm text-slate-500">Nunca foi emprestado.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
