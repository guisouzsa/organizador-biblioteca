@extends('layouts.app')

@section('title', 'Minha senha — '.config('app.name'))

@section('content')
    <div class="max-w-lg">
        <x-shared.page-header title="Alterar minha senha" :subtitle="auth()->user()->must_change_password ? 'Por segurança, defina uma nova senha antes de continuar.' : null" />

        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" novalidate>
            @csrf
            @method('PUT')
            <x-shared.input name="current_password" label="Senha atual" type="password" required :value="''" autocomplete="current-password" />
            <x-shared.input name="password" label="Nova senha" type="password" required :value="''" autocomplete="new-password" hint="Mínimo de 10 caracteres, com letras e números." />
            <x-shared.input name="password_confirmation" label="Repita a nova senha" type="password" required :value="''" autocomplete="new-password" />
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Salvar nova senha</button>
        </form>
    </div>
@endsection
