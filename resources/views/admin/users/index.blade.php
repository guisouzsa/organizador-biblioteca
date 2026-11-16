@extends('layouts.app')

@section('title', 'Usuários — '.config('app.name'))

@section('content')
    <x-shared.page-header title="Usuários do sistema" subtitle="Quem opera a biblioteca. Contas não são excluídas: são desativadas, preservando o histórico.">
        <a href="{{ route('users.create') }}" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Novo usuário</a>
    </x-shared.page-header>

    @if (session('credentials'))
        <div role="alert" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Senha temporária — mostrada apenas uma vez</p>
            <p class="mt-1">Entregue ao usuário por um canal seguro. Ele será obrigado a trocá-la no primeiro acesso.</p>
            <dl class="mt-3 grid gap-1 sm:grid-cols-[auto_1fr] sm:gap-x-4">
                <dt class="font-medium">E-mail</dt><dd class="font-mono">{{ session('credentials.email') }}</dd>
                <dt class="font-medium">Senha</dt><dd class="font-mono text-base select-all">{{ session('credentials.password') }}</dd>
            </dl>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">E-mail</th><th class="px-4 py-3">Perfil</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3">Último acesso</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $u)
                    <tr class="{{ $u->active ? '' : 'bg-slate-50 text-slate-500' }}">
                        <td class="px-4 py-3 font-medium">{{ $u->name }}@if ($u->id === auth()->id()) <span class="text-xs text-slate-400">(você)</span>@endif</td>
                        <td class="px-4 py-3">{{ $u->email }}</td>
                        <td class="px-4 py-3">{{ $u->role->label() }}</td>
                        <td class="px-4 py-3">@if ($u->active)<x-shared.badge tone="emerald">Ativo</x-shared.badge>@else<x-shared.badge>Desativado</x-shared.badge>@endif @if ($u->must_change_password)<x-shared.badge tone="amber">Senha temporária</x-shared.badge>@endif</td>
                        <td class="px-4 py-3">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'nunca' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('users.edit', $u) }}" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Editar</a>
                            <form method="POST" action="{{ route('users.reset-password', $u) }}" class="inline" data-once onsubmit="return confirm('Gerar uma nova senha temporária para {{ $u->name }}?')">@csrf
                                <button class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Redefinir senha</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
