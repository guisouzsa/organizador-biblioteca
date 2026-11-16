@extends('layouts.app')

@section('title', 'Reservas — '.config('app.name'))

@section('content')
    <x-shared.page-header title="Reservas" subtitle="Fila por livro, por ordem de chegada. Quando um exemplar é devolvido, ele fica retido para o primeiro da fila." />

    @can('create', \App\Models\Reservation::class)
        <form method="POST" action="{{ route('reservations.store') }}" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-3" novalidate data-once>
            @csrf
            <div class="sm:col-span-1">
                <label for="book_id" class="block text-sm font-medium text-slate-700">Livro</label>
                <select id="book_id" name="book_id" required class="mt-1 block w-full rounded-md border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600">
                    <option value="">Escolha um livro…</option>
                    @foreach ($reservable as $option)<option value="{{ $option->id }}" @selected((string) old('book_id', $book?->id) === (string) $option->id)>{{ $option->title }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Só aparecem livros com exemplares e nenhum disponível.</p>
                @error('book_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <x-shared.input name="borrower_q" label="Leitor (matrícula ou nome)" :value="''" required />
            <div class="flex items-end"><button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Reservar</button></div>
            <p class="text-xs text-slate-500 sm:col-span-3">A reserva só é aceita quando não há exemplar disponível do livro.</p>
        </form>
    @endcan

    <div class="mb-4 flex gap-2 text-sm">
        <a href="{{ route('reservations.index', array_filter(['livro' => $book?->id])) }}" class="rounded-full px-4 py-1.5 font-medium {{ ! $history ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300' }}">Ativas</a>
        <a href="{{ route('reservations.index', array_filter(['livro' => $book?->id, 'historico' => 1])) }}" class="rounded-full px-4 py-1.5 font-medium {{ $history ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300' }}">Histórico</a>
    </div>

    @if ($reservations->isEmpty())
        <x-shared.empty-state :title="$history ? 'Nenhuma reserva no histórico.' : 'Nenhuma reserva ativa.'" text="Reservas aparecem aqui quando um livro não tem exemplar disponível." icon="bookmark" />
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Livro</th><th class="px-4 py-3">Leitor</th><th class="px-4 py-3">Pedida em</th><th class="px-4 py-3">Fila</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($reservations as $r)
                        <tr>
                            <td class="px-4 py-3 font-medium"><a href="{{ route('books.show', $r->book) }}" class="hover:underline">{{ $r->book->title }}</a></td>
                            <td class="px-4 py-3"><a href="{{ route('borrowers.show', $r->borrower) }}" class="hover:underline">{{ $r->borrower->name }}</a></td>
                            <td class="px-4 py-3">{{ $r->requested_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $r->queuePosition() ? $r->queuePosition().'º' : '—' }}</td>
                            <td class="px-4 py-3">
                                <x-shared.badge :tone="['waiting' => 'slate', 'ready' => 'sky', 'fulfilled' => 'emerald', 'cancelled' => 'rose', 'expired' => 'amber'][$r->status->value]">{{ $r->status->label() }}</x-shared.badge>
                                @if ($r->copy)<span class="ml-1 font-mono text-xs">{{ $r->copy->code() }}</span>@endif
                                @if ($r->expires_at && $r->status->value === 'ready')<span class="ml-1 text-xs text-slate-500">até {{ $r->expires_at->format('d/m H:i') }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('cancel', $r)
                                    <form method="POST" action="{{ route('reservations.cancel', $r) }}" class="inline" data-once onsubmit="return confirm('Cancelar esta reserva?')">@csrf<button class="rounded-md px-3 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50">Cancelar</button></form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $reservations->links() }}</div>
    @endif
@endsection
