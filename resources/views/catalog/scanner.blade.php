@extends('layouts.app')

@section('title', 'Escanear livro — '.config('app.name'))

@push('head')
    @vite('resources/js/scanner.js')
@endpush

@section('content')
    <div class="max-w-3xl" id="scanner-app" data-lookup-url="{{ route('books.lookup') }}">
        <h1 class="text-2xl">Escanear livro</h1>
        <p class="mt-1 text-sm text-slate-600">Aponte a câmera para o código de barras do verso do livro (ISBN). Se preferir, digite o número abaixo.</p>

        <div id="scanner-status" role="status" aria-live="polite" data-state="idle"
             class="mt-5 flex items-center gap-3 rounded-lg border px-4 py-3 text-sm data-[state=idle]:border-slate-200 data-[state=idle]:bg-white data-[state=requesting]:border-sky-200 data-[state=requesting]:bg-sky-50 data-[state=scanning]:border-indigo-200 data-[state=scanning]:bg-indigo-50 data-[state=detected]:border-emerald-200 data-[state=detected]:bg-emerald-50 data-[state=searching]:border-sky-200 data-[state=searching]:bg-sky-50 data-[state=done]:border-emerald-200 data-[state=done]:bg-emerald-50 data-[state=error]:border-rose-200 data-[state=error]:bg-rose-50">
            <span id="scanner-status-text">Câmera desligada.</span>
        </div>

        <div class="relative mt-4 aspect-[4/3] w-full overflow-hidden rounded-xl bg-slate-900 ring-1 ring-slate-300">
            <video id="scanner-video" class="h-full w-full object-cover" playsinline muted autoplay></video>
            <div id="scanner-placeholder" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-300">
                A imagem da câmera aparecerá aqui.
            </div>
            <div id="scanner-frame" class="pointer-events-none absolute inset-x-[10%] top-1/2 hidden h-1/3 -translate-y-1/2 rounded-lg border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" aria-hidden="true"></div>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <button type="button" id="scanner-toggle" class="btn btn-primary">
                Iniciar câmera
            </button>
            <div class="min-w-48 flex-1 sm:flex-none">
                <label for="scanner-device" class="block text-xs font-medium text-slate-600">Câmera</label>
                <select id="scanner-device" disabled class="btn btn-secondary mt-1 w-full">
                    <option value="">Automática (traseira)</option>
                </select>
            </div>
        </div>

        <form id="manual-isbn-form" class="panel mt-6 p-4" novalidate>
            <label for="manual-isbn" class="block text-sm font-medium text-slate-700">Digitar ISBN manualmente</label>
            <div class="mt-1 flex gap-2">
                <input id="manual-isbn" name="isbn" type="text" inputmode="text" autocomplete="off" placeholder="978-85-..." maxlength="40"
                       class="field">
                <button type="submit" class="btn btn-primary shrink-0">Pesquisar</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Aceita ISBN-13 e ISBN-10, com ou sem hifens.</p>
        </form>

        <div id="scanner-result" class="mt-6" tabindex="-1" aria-live="polite"></div>

        <p class="mt-8 text-sm text-slate-600">
            A câmera não funciona? <a href="{{ route('books.create') }}" class="font-medium text-indigo-700 underline">Cadastre o livro manualmente</a>.
        </p>
    </div>
@endsection
