@extends('layouts.guest')

@section('title', $title.' — '.config('app.name'))

@section('content')
    <div class="panel p-6">
        <span class="modal__icon" data-tone="{{ $tone ?? 'warning' }}" aria-hidden="true"><x-shared.icon :name="$icon ?? 'alert'" class="h-5 w-5" /></span>
        <h1 class="mt-4 text-2xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-700">{{ $message }}</p>
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="back-link">← Voltar</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Ir para o início</a>
        </div>
    </div>
@endsection
