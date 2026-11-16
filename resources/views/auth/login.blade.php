@extends('layouts.guest')

@section('title', 'Entrar — '.config('app.name'))

@section('content')
    <h1 class="text-lg font-semibold">Entrar no sistema</h1>
    <p class="mt-1 text-sm text-slate-600">Use o e-mail e a senha fornecidos pela biblioteca.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-shared.input name="email" label="E-mail" type="email" required autocomplete="username" autofocus />
        <x-shared.input name="password" label="Senha" type="password" required autocomplete="current-password" :value="''" />
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"> Manter conectado neste computador
        </label>
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Entrar</button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="{{ route('password.request') }}" class="font-medium text-indigo-700 hover:underline">Esqueci minha senha</a></p>
@endsection
