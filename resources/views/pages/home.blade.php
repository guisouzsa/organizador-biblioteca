@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <x-layout.container class="py-16">
        <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        <p class="mt-2 text-slate-600">Aplicação Laravel + Blade + Tailwind CSS em execução.</p>
    </x-layout.container>
@endsection
