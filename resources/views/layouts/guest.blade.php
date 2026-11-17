<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#c4c4c4">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page font-sans antialiased">
    <main class="auth-layout">
        <section class="auth-story" aria-labelledby="auth-brand-title">
            <a href="{{ route('home') }}" class="wordmark auth-story__brand" aria-label="Voltar à página inicial">
                <span class="wordmark__mark" aria-hidden="true">OE</span><span>{{ config('app.name') }}</span>
            </a>
            <div class="auth-story__content">
                <p class="marketing-kicker">Biblioteca em movimento</p>
                <h1 id="auth-brand-title">Organização que aproxima pessoas e livros.</h1>
                <p>Uma visão clara do acervo e da circulação para apoiar o trabalho diário da biblioteca escolar.</p>
                <div class="auth-books" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            </div>
            <p class="auth-story__footer">Gestão de biblioteca escolar</p>
        </section>
        <section class="auth-form-area" aria-label="Autenticação">
            <div class="auth-form-wrap">
                <a href="{{ route('home') }}" class="auth-back">← Voltar ao início</a>
                <x-ui.error-summary />
                @yield('content')
            </div>
        </section>
    </main>
    <x-ui.feedback />
</body>
</html>
