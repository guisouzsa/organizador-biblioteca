@extends('layouts.app')

@section('title', 'Auditoria — '.config('app.name'))

@php($input = 'field')

@section('content')
    <x-shared.page-header title="Histórico de atividades" subtitle="Registro de quem fez o quê. Não pode ser editado nem apagado." />

    @if ($restricted)
        <p class="mb-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Você vê as ações do catálogo e do acervo e as suas próprias. A auditoria completa é do Administrador.</p>
    @endif

    <form method="GET" action="{{ route('audit.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div><label for="evento" class="block text-sm font-medium text-slate-700">Tipo</label>
            <select id="evento" name="evento" class="{{ $input }}"><option value="">Todos</option>
                @foreach ($events as $prefix => $label)<option value="{{ $prefix }}" @selected(($filters['evento'] ?? '') === $prefix)>{{ $label }}</option>@endforeach
            </select></div>
        @if ($users->isNotEmpty())
            <div><label for="usuario" class="block text-sm font-medium text-slate-700">Usuário</label>
                <select id="usuario" name="usuario" class="{{ $input }}"><option value="">Todos</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['usuario'] ?? null) == $u->id)>{{ $u->name }}</option>@endforeach
                </select></div>
        @endif
        <div><label for="de" class="block text-sm font-medium text-slate-700">De</label><input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}" class="{{ $input }}"></div>
        <div><label for="ate" class="block text-sm font-medium text-slate-700">Até</label><input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}" class="{{ $input }}"></div>
        <div class="flex items-end"><button class="btn btn-primary">Filtrar</button></div>
    </form>

    @if ($logs->isEmpty())
        <div class="mt-8"><x-shared.empty-state title="Nenhuma atividade encontrada." text="As ações importantes aparecem aqui conforme o sistema é usado." icon="clock" /></div>
    @else
        <div class="panel mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Quando</th><th class="px-4 py-3">Quem</th><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Registro</th><th class="px-4 py-3">Alterações</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-2.5">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $log->event }}</td>
                            <td class="px-4 py-2.5">{{ $log->subjectLabel() }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">
                                @foreach (array_keys(($log->new_values ?? []) + ($log->old_values ?? [])) as $key)
                                    @php($o = $log->old_values[$key] ?? null) @php($n = $log->new_values[$key] ?? null)
                                    <div><span class="font-medium">{{ $key }}:</span>
                                        @if ($o !== null)<span class="text-rose-700 line-through">{{ is_array($o) ? json_encode($o, JSON_UNESCAPED_UNICODE) : \Illuminate\Support\Str::limit((string) $o, 60) }}</span> →@endif
                                        <span class="text-emerald-700">{{ is_array($n) ? json_encode($n, JSON_UNESCAPED_UNICODE) : \Illuminate\Support\Str::limit((string) $n, 60) }}</span></div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
@endsection
