@extends('layouts.app')

@section('title', 'Novo livro — '.config('app.name'))

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-2xl font-semibold tracking-tight">Cadastrar livro manualmente</h1>
        <p class="mt-1 text-sm text-slate-600">
            Prefere usar a câmera? <a href="{{ route('books.scanner') }}" class="font-medium text-indigo-700 underline">Escanear o código de barras</a>.
        </p>

        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('catalog.partials.form', [
                'action' => route('books.store'),
                'method' => 'POST',
                'submitLabel' => 'Salvar livro',
                'suggestedCategories' => [],
            ])
        </div>
    </div>
@endsection
