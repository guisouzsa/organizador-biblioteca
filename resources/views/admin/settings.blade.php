@extends('layouts.app')

@section('title', 'Configurações — '.config('app.name'))

@section('content')
    <div class="max-w-2xl">
        <x-shared.page-header title="Configurações da biblioteca" />

        <form method="POST" action="{{ route('settings.update') }}" class="panel space-y-4 p-6" novalidate data-once>
            @csrf @method('PUT')
            @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Corrija os campos destacados.</div>@endif

            <x-shared.input name="library_name" label="Nome da biblioteca" :value="$libraryName" required />
            <x-shared.input name="barcode_prefix" label="Prefixo do código de barras dos exemplares" :value="$barcodePrefix" required maxlength="8" class="max-w-xs"
                            hint="Ex.: EX gera EX-000123. Vale só para códigos criados daqui em diante; os existentes não mudam." />
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="accession_required" value="1" @checked(old('accession_required', $accessionRequired)) class="mt-0.5 rounded border-slate-300 text-indigo-600">
                <span><span class="font-medium">Exigir tombo (nº patrimonial)</span> ao registrar exemplares. Com isso, as cópias são registradas uma por vez.</span>
            </label>
            <button type="submit" class="btn btn-primary">Salvar configurações</button>
        </form>
    </div>
@endsection
