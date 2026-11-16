<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-indigo-50 font-sans text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="mb-6 flex items-center justify-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-600 text-white"><x-shared.icon name="book" class="h-6 w-6" /></span>
            <span class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</span>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <x-shared.flash />
            @yield('content')
        </div>
    </main>
</body>
</html>
