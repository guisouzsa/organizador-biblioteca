@extends('layouts.app')

@section('title', 'Escanear livro — '.config('app.name'))

@push('head')
    @vite('resources/js/scanner.js')
@endpush

@section('content')
    <div class="max-w-3xl" id="scanner-app" data-lookup-url="{{ route('books.lookup') }}" data-manual-url="{{ route('books.create') }}">
        <x-shared.page-header eyebrow="Catálogo" title="Escanear livro" subtitle="Aponte a câmera para o código de barras do verso do livro (ISBN). Se preferir, digite o número abaixo." />

        <div id="scanner-status" role="status" aria-live="polite" data-state="idle"
             class="scanner-status flex items-center gap-3 rounded-lg border px-4 py-3 text-sm data-[state=idle]:border-cinza-5 data-[state=idle]:bg-white data-[state=requesting]:border-sky-200 data-[state=requesting]:bg-sky-50 data-[state=scanning]:border-indigo-200 data-[state=scanning]:bg-indigo-50 data-[state=detected]:border-emerald-200 data-[state=detected]:bg-emerald-50 data-[state=searching]:border-sky-200 data-[state=searching]:bg-sky-50 data-[state=done]:border-emerald-200 data-[state=done]:bg-emerald-50 data-[state=error]:border-rose-200 data-[state=error]:bg-rose-50">
            <span id="scanner-status-text">Câmera desligada.</span>
        </div>

        {{-- Indicador visual de carregamento com patinhas (efeitos.css) --}}
        <div id="scanner-loading" aria-hidden="true" class="hidden mt-2 justify-center">
            <span class="carregando" role="presentation"><span class="carregando__meio"></span></span>
        </div>

        <div class="relative mt-4 aspect-[4/3] w-full overflow-hidden rounded-xl bg-slate-900 ring-1 ring-cinza-4">
            <video id="scanner-video" class="h-full w-full object-cover" playsinline muted autoplay></video>
            <div id="scanner-placeholder" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-300">
                A imagem da câmera aparecerá aqui.
            </div>
            <div id="scanner-frame" class="pointer-events-none absolute inset-x-[10%] top-1/2 hidden h-1/3 -translate-y-1/2 rounded-lg border-2 border-cinza-5 shadow-[0_0_0_9999px_#808080]" aria-hidden="true"></div>
        </div>

        {{-- Ações principais ao alcance do polegar. O cadastro manual fica SEMPRE visível, com ou sem câmera. --}}
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button type="button" id="scanner-toggle" class="btn btn-primary btn-lg">
                <x-shared.icon name="camera" class="h-5 w-5" /> <span id="scanner-toggle-text">Iniciar câmera</span>
            </button>
            <a href="{{ route('books.create') }}" id="manual-register" class="btn btn-secondary btn-lg">Cadastrar livro manualmente</a>
        </div>

        <div class="mt-4 max-w-sm">
            <label for="scanner-device" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Câmera</label>
            <select id="scanner-device" disabled class="field">
                <option value="">Automática (traseira)</option>
            </select>
        </div>

        <form id="manual-isbn-form" class="panel mt-6 p-4" novalidate>
            <label for="manual-isbn" class="mb-1 block text-sm font-medium text-slate-800">Digitar ISBN manualmente</label>
            <div class="flex gap-2">
                <input id="manual-isbn" name="isbn" type="text" inputmode="text" autocomplete="off" placeholder="978-85-..." maxlength="40" class="field">
                <button type="submit" class="btn btn-primary shrink-0">Pesquisar</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Aceita ISBN-13 e ISBN-10, com ou sem hifens.</p>
        </form>

        <div id="scanner-result" class="mt-6" tabindex="-1" aria-live="polite"></div>
    </div>
@endsection
