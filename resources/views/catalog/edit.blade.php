@extends('layouts.app')

@section('title', 'Editar '.$book->title.' — '.config('app.name'))

@section('content')
    <div class="max-w-3xl">
        <x-shared.page-header title="Editar registro" :subtitle="$book->title" />

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('catalog.partials.form', [
                'action' => route('books.update', $book),
                'method' => 'PUT',
                'submitLabel' => 'Salvar alterações',
                'suggestedCategories' => [],
            ])
        </div>
        <p class="mt-3 text-xs text-slate-500">As alterações ficam registradas na auditoria (quem mudou, o quê e quando).</p>
    </div>
@endsection
