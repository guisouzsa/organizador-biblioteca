@extends('layouts.guest')

@section('title', 'Recuperar acesso — '.config('app.name'))

@section('content')
    <h1 class="text-lg">Recuperar acesso</h1>
    <p class="mt-1 text-sm text-slate-600">Informe seu e-mail. Se ele estiver cadastrado, você receberá as instruções. Se o envio de e-mail não estiver configurado, peça a redefinição a um administrador.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-shared.input name="email" label="E-mail" type="email" required autofocus />
        <button type="submit" class="btn btn-primary w-full">Enviar instruções</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="font-medium text-indigo-700 hover:underline">Voltar ao login</a></p>
@endsection
