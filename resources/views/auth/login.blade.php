@extends('layouts.guest')

@section('title', 'Entrar — '.config('app.name'))

@section('content')
    <p class="marketing-kicker">Área restrita</p>
    <h2 class="auth-form-title">Acesse sua conta</h2>
    <p class="auth-form-intro">Use o e-mail e a senha fornecidos pela biblioteca.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" novalidate data-once>
        @csrf
        <x-shared.input name="email" label="E-mail" type="email" required autocomplete="username" inputmode="email" autofocus />
        <x-shared.input name="password" label="Senha" type="password" required autocomplete="current-password" :value="''" />
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
            <label class="flex min-h-11 items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1" class="rounded-sm border-slate-400 text-indigo-600"> Manter conectado
            </label>
            <a href="{{ route('password.request') }}" class="auth-inline-link">Esqueci minha senha</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
    </form>
@endsection
