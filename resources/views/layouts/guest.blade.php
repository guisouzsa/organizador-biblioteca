<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f5f5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('decor/efeitos.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('decor/logo.png') }}">
</head>
<body class="min-h-screen font-sans antialiased fundo-patas">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-5 py-10">
        <div class="mb-6">
            <a href="{{ route('home') }}" class="inline-block">
                <img src="{{ asset('decor/logo.png') }}" alt="{{ config('app.name') }} — página inicial" width="500" height="500" class="h-32 w-auto object-contain">
            </a>
        </div>
        <div>
            <div class="espia panel p-5 sm:p-6" style="margin-top: calc(3.25rem + 1rem)">
                <img class="espia__gato" src="{{ asset('decor/gato-espiando.svg') }}" alt="" aria-hidden="true">
                <x-ui.error-summary />
                @yield('content')
            </div>
        </div>
    </main>
    <x-ui.feedback />
</body>
</html>
