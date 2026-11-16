@extends('layouts.app')

@section('title', 'Novo leitor — '.config('app.name'))

@section('content')
    <div class="max-w-2xl">
        <x-shared.page-header title="Cadastrar leitor" subtitle="Dados mínimos: nome, matrícula e turma." />
        <div class="panel p-6">
            @include('borrowers.partials.form', ['action' => route('borrowers.store'), 'method' => 'POST', 'borrower' => null, 'submitLabel' => 'Cadastrar leitor'])
        </div>
    </div>
@endsection
