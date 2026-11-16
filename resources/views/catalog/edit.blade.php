@extends('layouts.app')

@section('title', 'Editar '.$book->title.' — '.config('app.name'))

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-2xl font-semibold tracking-tight">Editar registro</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $book->title }}</p>

        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('catalog.partials.form', [
                'action' => route('books.update', $book),
                'method' => 'PUT',
                'submitLabel' => 'Salvar alterações',
                'suggestedCategories' => [],
            ])
        </div>
    </div>
@endsection
