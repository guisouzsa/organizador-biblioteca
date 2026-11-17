@extends('layouts.app')

@section('title', 'Registrar exemplares — '.config('app.name'))

@section('content')
    <div class="max-w-2xl">
        <x-shared.page-header title="Registrar exemplares" :subtitle="$book->title" />

        <div class="mb-5 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
            Um <strong>livro</strong> é a edição (identificada pelo ISBN). Cada <strong>exemplar</strong> é uma cópia física, com código de barras próprio.
            Ao registrar várias cópias, cada uma recebe um código diferente.
        </div>

        <form method="POST" action="{{ route('books.copies.store', $book) }}" class="panel space-y-4 p-6" novalidate data-once>
            @csrf
            <p class="required-note"><span class="required-mark" aria-hidden="true">*</span> indica campo obrigatório.</p>
            @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Corrija os campos destacados.</div>@endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-shared.input name="quantity" label="Quantidade de cópias" type="number" :value="'1'" required min="1" max="100" />
                <x-shared.select name="condition" label="Conservação" :options="$conditions" value="good" required />
                <x-shared.select name="location_id" label="Localização" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->fullName()])->all()" placeholder="Sem localização" hint="Cadastre localizações em Administração → Localizações." />
                <x-shared.input name="acquired_at" label="Data de aquisição" type="date" :value="''" />
                <x-shared.input name="acquisition_source" label="Origem" :value="''" hint="Ex.: compra, doação, PNLD." />
                <x-shared.input name="accession_number" label="Tombo (nº patrimonial)" :value="''" hint="Só ao registrar uma cópia por vez." />
                <x-shared.textarea name="notes" label="Observações" class="sm:col-span-2" />
            </div>

            <div class="form-actions flex gap-3 border-t border-slate-200 pt-5" data-form-actions>
                <button type="submit" class="btn btn-primary">Registrar e gerar etiquetas</button>
                <a href="{{ route('books.show', $book) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
