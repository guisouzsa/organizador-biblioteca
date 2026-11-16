@extends('layouts.app')

@section('title', 'Etiquetas — '.config('app.name'))

@push('head')
    @vite('resources/js/labels.js')
    <style>
        @media print {
            #sidebar, [data-sidebar-backdrop], header, .no-print { display: none !important; }
            .lg\:pl-64 { padding-left: 0 !important; }
            main { padding: 0 !important; max-width: none !important; }
            body { background: #fff !important; }
            .label-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #000 !important; }
        }
    </style>
@endpush

@section('content')
    <div class="no-print">
        <x-shared.page-header title="Etiquetas" subtitle="Imprima e cole no exemplar. O código também aparece escrito abaixo das barras.">
            <button type="button" data-print class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Imprimir etiquetas</button>
            <a href="{{ route('copies.index') }}" class="rounded-md px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Ir ao acervo</a>
        </x-shared.page-header>
    </div>

    @if ($copies->isEmpty())
        <x-shared.empty-state title="Nenhum exemplar selecionado." text="Abra um exemplar e escolha “Imprimir etiqueta”." icon="stack" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($copies as $copy)
                <div class="label-card rounded-lg bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
                    <p class="truncate text-sm font-semibold">{{ \Illuminate\Support\Str::limit($copy->book->title, 42) }}</p>
                    <svg data-barcode="{{ $copy->code() }}" class="mx-auto mt-2 max-w-full" role="img" aria-label="Código de barras {{ $copy->code() }}"></svg>
                    <p class="mt-1 text-xs text-slate-600">
                        @if ($copy->accession_number)Tombo {{ $copy->accession_number }} · @endif{{ $copy->location?->name ?? 'Sem localização' }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
@endsection
