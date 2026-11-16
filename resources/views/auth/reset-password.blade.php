@extends('layouts.guest')

@section('title', 'Nova senha — '.config('app.name'))

@section('content')
    <h1 class="text-lg font-semibold">Definir nova senha</h1>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-shared.input name="email" label="E-mail" type="email" :value="$email" required />
        <x-shared.input name="password" label="Nova senha" type="password" required hint="Mínimo de 10 caracteres, com letras e números." :value="''" autocomplete="new-password" />
        <x-shared.input name="password_confirmation" label="Repita a nova senha" type="password" required :value="''" autocomplete="new-password" />
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Salvar nova senha</button>
    </form>
@endsection
