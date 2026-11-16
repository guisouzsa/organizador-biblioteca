@extends('layouts.app')

@section('title', 'Localizações — '.config('app.name'))

@section('content')
    <x-shared.page-header title="Localizações físicas" subtitle="Onde os exemplares ficam guardados (sala, estante, prateleira)." />

    <form method="POST" action="{{ route('locations.store') }}" class="panel mb-6 grid gap-3 p-4 sm:grid-cols-4" novalidate data-once>
        @csrf
        <x-shared.input name="name" label="Nova localização" :value="''" required placeholder="Ex.: Estante A" />
        <x-shared.input name="code" label="Código (opcional)" :value="''" placeholder="Ex.: EA" />
        <x-shared.select name="parent_id" label="Dentro de" :options="$parents->pluck('name', 'id')->all()" placeholder="— (nível principal)" />
        <div class="flex items-end"><button class="btn btn-primary">Adicionar</button></div>
    </form>

    @if ($locations->isEmpty())
        <x-shared.empty-state title="Nenhuma localização cadastrada." text="Cadastre ao menos uma para organizar o acervo. Exemplares podem ficar sem localização." icon="pin" />
    @else
        <div class="panel overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Código</th><th class="px-4 py-3">Dentro de</th><th class="px-4 py-3">Exemplares</th><th class="px-4 py-3">Ativa</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($locations as $location)
                        @php($f = 'loc'.$location->id)
                        <tr>
                            <td class="px-4 py-2">
                                <form id="{{ $f }}" method="POST" action="{{ route('locations.update', $location) }}">@csrf @method('PUT')</form>
                                <input form="{{ $f }}" name="name" value="{{ $location->name }}" required aria-label="Nome" class="btn btn-secondary w-full">
                            </td>
                            <td class="px-4 py-2"><input form="{{ $f }}" name="code" value="{{ $location->code }}" aria-label="Código" class="btn btn-secondary w-24"></td>
                            <td class="px-4 py-2">
                                <select form="{{ $f }}" name="parent_id" aria-label="Dentro de" class="btn btn-secondary">
                                    <option value="">—</option>
                                    @foreach ($parents->where('id', '!=', $location->id) as $parent)<option value="{{ $parent->id }}" @selected($location->parent_id === $parent->id)>{{ $parent->name }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2"><a href="{{ route('copies.index', ['local' => $location->id]) }}" class="text-indigo-700 hover:underline">{{ $location->copies_count }}</a></td>
                            <td class="px-4 py-2"><input form="{{ $f }}" type="checkbox" name="active" value="1" @checked($location->active) aria-label="Ativa" class="rounded border-slate-300 text-indigo-600"></td>
                            <td class="px-4 py-2 text-right"><button form="{{ $f }}" class="btn btn-secondary">Salvar</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500">Localizações com exemplares não são excluídas: desative-as para impedir novos usos.</p>
    @endif
@endsection
