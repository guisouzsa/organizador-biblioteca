<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f1ea">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-5 py-10">
        <div class="mb-8">
            <p class="eyebrow">Sistema de biblioteca</p>
            <p class="mt-1 font-serif text-3xl font-medium leading-tight tracking-tight">{{ config('app.name') }}</p>
            <div class="rule mt-4"></div>
        </div>
        <div>
            <x-shared.flash />
            @yield('content')
        </div>
    </main>
</body>
</html>
