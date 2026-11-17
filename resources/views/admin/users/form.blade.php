@extends('layouts.app')

@section('title', ($user ? 'Editar usuário' : 'Novo usuário').' — '.config('app.name'))

@section('content')
    <div class="max-w-xl">
        <x-shared.page-header :title="$user ? 'Editar usuário' : 'Novo usuário'" :subtitle="$user?->email" />

        <form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}" class="panel space-y-4 p-6" novalidate data-once>
            @csrf
            <p class="required-note"><span class="required-mark" aria-hidden="true">*</span> indica campo obrigatório.</p>
            @if ($user) @method('PUT') @endif
            @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Corrija os campos destacados.</div>@endif

            <x-shared.input name="name" label="Nome" :value="$user?->name ?? ''" required />
            @unless ($user)<x-shared.input name="email" label="E-mail (login)" type="email" :value="''" required />@endunless
            <x-shared.select name="role" label="Perfil" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :value="$user?->role->value ?? 'assistant'" required />

            <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                <p><strong>Administrador:</strong> tudo. <strong>Bibliotecário:</strong> catálogo, acervo, leitores, circulação e relatórios.
                <strong>Auxiliar:</strong> balcão (empréstimo, devolução, reservas) e consultas. <strong>Consulta:</strong> somente o catálogo.</p>
            </div>

            @if ($user)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active)) class="rounded border-slate-300 text-indigo-600"> Conta ativa</label>
            @else
                <p class="text-xs text-slate-500">Uma senha temporária será gerada e mostrada uma única vez. O usuário deverá trocá-la no primeiro acesso.</p>
                <input type="hidden" name="active" value="1">
            @endif

            <div class="flex gap-3 border-t border-slate-200 pt-5">
                <button type="submit" class="btn btn-primary">{{ $user ? 'Salvar' : 'Criar usuário' }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
