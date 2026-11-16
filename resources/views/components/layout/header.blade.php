@php
    $links = [
        ['route' => 'books.index', 'label' => 'Catálogo', 'active' => request()->routeIs('books.index', 'books.show', 'books.edit')],
        ['route' => 'books.scanner', 'label' => 'Escanear livro', 'active' => request()->routeIs('books.scanner')],
        ['route' => 'books.create', 'label' => 'Novo livro', 'active' => request()->routeIs('books.create')],
    ];
@endphp

<header class="border-b border-slate-200 bg-white">
    <x-layout.container class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3">
        <a href="{{ route('books.index') }}" class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</a>

        <nav aria-label="Principal" class="flex flex-wrap gap-1">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @if ($link['active']) aria-current="page" @endif
                   class="rounded-md px-3 py-2 text-sm font-medium {{ $link['active'] ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    </x-layout.container>
</header>
