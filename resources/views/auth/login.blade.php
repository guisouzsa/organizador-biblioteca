@extends('layouts.guest')

@section('title', 'Entrar — '.config('app.name'))

@section('content')
    <h1 class="text-2xl">Entrar</h1>
    <p class="mt-1 text-sm text-slate-600">Use o e-mail e a senha fornecidos pela biblioteca.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" novalidate data-once>
        @csrf
        <x-shared.input name="email" label="E-mail" type="email" required autocomplete="username" inputmode="email" autofocus />
        <x-shared.input name="password" label="Senha" type="password" required autocomplete="current-password" :value="''" />
        <label class="flex min-h-11 items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" value="1" class="rounded-sm border-slate-400 text-indigo-600"> Manter conectado neste dispositivo
        </label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
    </form>

    <p class="mt-5 text-sm"><a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center font-medium text-indigo-700 hover:underline">Esqueci minha senha</a></p>
@endsection
