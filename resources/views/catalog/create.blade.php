@extends('layouts.app')

@section('title', 'Novo livro — '.config('app.name'))

@section('content')
    <div class="max-w-3xl">
        <x-shared.page-header title="Cadastrar livro manualmente" subtitle="Informe os dados que você conhece. Nada é preenchido por suposição." />
        <p class="-mt-3 mb-5 text-sm text-slate-600">
            Prefere usar a câmera? <a href="{{ route('books.scanner') }}" class="font-medium text-indigo-700 underline">Escanear o código de barras</a>.
        </p>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('catalog.partials.form', [
                'action' => route('books.store'),
                'method' => 'POST',
                'submitLabel' => 'Salvar livro',
                'suggestedCategories' => [],
            ])
        </div>
    </div>
@endsection
