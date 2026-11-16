@extends('layouts.app')

@section('title', 'Relatórios — '.config('app.name'))

@php
    $input = 'field';
    $usesPeriod = in_array($type, ['mais-emprestados', 'movimentacoes', 'turmas'], true);
@endphp

@section('content')
    <x-shared.page-header title="Relatórios" subtitle="Todos os dados vêm do banco; nada é estimado.">
        @can('report.export')
            <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-secondary">Exportar CSV</a>
        @endcan
    </x-shared.page-header>

    <div class="mb-4 flex flex-wrap gap-1" role="tablist" aria-label="Tipo de relatório">
        @foreach ($types as $key => $label)
            <a href="{{ route('reports.index', ['tipo' => $key]) }}" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}"
               class="rounded-md px-4 py-1.5 text-sm font-medium {{ $type === $key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($usesPeriod || $type === 'disponibilidade')
        <form method="GET" action="{{ route('reports.index') }}" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-4">
            <input type="hidden" name="tipo" value="{{ $type }}">
            @if ($usesPeriod)
                <div><label for="de" class="block text-sm font-medium text-slate-700">De</label><input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}" class="{{ $input }}"></div>
                <div><label for="ate" class="block text-sm font-medium text-slate-700">Até</label><input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}" class="{{ $input }}"></div>
            @else
                <div class="sm:col-span-2"><label for="categoria" class="block text-sm font-medium text-slate-700">Disciplina / categoria</label>
                    <select id="categoria" name="categoria" class="{{ $input }}"><option value="">Todas</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['categoria'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select></div>
            @endif
            <div class="flex items-end"><button class="btn btn-primary">Aplicar</button></div>
        </form>
    @endif

    <p class="mb-2 text-xs text-slate-500">
        {{ $report['title'] }}@if ($report['period']) · período {{ $report['period'] }}@endif · gerado em {{ now()->format('d/m/Y H:i') }} · {{ count($report['rows']) }} linha(s)
    </p>

    @if (count($report['rows']) === 0)
        <x-shared.empty-state title="Sem dados para este relatório." :text="$usesPeriod ? 'Nenhum registro no período selecionado.' : 'Ainda não há registros correspondentes.'" icon="chart" />
    @else
        <div class="panel overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>@foreach ($report['columns'] as $col)<th class="px-4 py-3">{{ $col }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($report['rows'] as $row)
                        <tr class="hover:bg-slate-50">@foreach ($row as $cell)<td class="px-4 py-2.5">{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
