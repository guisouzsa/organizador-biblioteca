@extends('layouts.app')

@section('title', 'Editar leitor — '.config('app.name'))

@section('content')
    <div class="max-w-2xl">
        <x-shared.page-header title="Editar leitor" :subtitle="$borrower->name" />
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('borrowers.partials.form', ['action' => route('borrowers.update', $borrower), 'method' => 'PUT', 'borrower' => $borrower, 'submitLabel' => 'Salvar alterações'])
        </div>
    </div>
@endsection
