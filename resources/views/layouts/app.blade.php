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
    @stack('head')
</head>
<body class="min-h-screen font-sans antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Ir para o conteúdo</a>

    @php
        $user = auth()->user();
        $libraryName = app(\App\Services\Admin\SettingsService::class)->libraryName();
        $mobileItems = array_values(array_filter([
            $user->can('dashboard.view') ? ['dashboard', 'home', 'Início', request()->routeIs('dashboard')] : null,
            $user->can('catalog.view') ? ['books.index', 'book', 'Catálogo', request()->routeIs('books.*')] : null,
            $user->can('loan.create') ? ['circulation.desk', 'swap', 'Balcão', request()->routeIs('circulation.*')] : null,
            $user->can('borrower.view') ? ['borrowers.index', 'users', 'Leitores', request()->routeIs('borrowers.*')] : null,
        ]));
    @endphp

    {{-- Cabeçalho compacto (celular/tablet) --}}
    <header data-app-header class="sticky top-0 z-30 flex h-14 items-center justify-between gap-2 border-b border-slate-200 bg-white/95 px-4 backdrop-blur-0 lg:hidden"
            style="padding-top: env(safe-area-inset-top)">
        <a href="{{ route($user->can('dashboard.view') ? 'dashboard' : 'books.index') }}" class="inline-flex min-h-11 items-center">
            <img src="{{ asset('decor/logo.png') }}" alt="" width="500" height="500" class="h-11 w-11 shrink-0 object-contain">
            <span class="ml-2 truncate font-semibold text-slate-900">{{ $libraryName }}</span>
        </a>
        @can('catalog.view')
            <a href="{{ route('books.index') }}#q" data-focus-search aria-label="Pesquisar no catálogo"
               class="grid h-11 w-11 shrink-0 place-items-center rounded-md text-slate-700 hover:bg-slate-100">
                <x-shared.icon name="search" class="h-5 w-5" />
            </a>
        @endcan
    </header>
    <div data-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-slate-900/40 lg:hidden"></div>

    {{-- Menu completo: coluna fixa no desktop; painel lateral no celular (acionado por "Mais") --}}
    <aside id="sidebar" aria-label="Navegação principal" data-open="false"
           class="fixed inset-y-0 z-50 flex w-[19rem] max-w-[88vw] flex-col border-slate-200 bg-white transition-transform duration-200
                  max-lg:right-0 max-lg:translate-x-full max-lg:border-l max-lg:data-[open=true]:translate-x-0
                  lg:left-0 lg:w-64 lg:border-r">
        <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-slate-200 px-5">
            <a href="{{ route($user->can('dashboard.view') ? 'dashboard' : 'books.index') }}" class="inline-flex min-w-0 items-center">
                <img src="{{ asset('decor/logo.png') }}" alt="" width="500" height="500" class="h-12 w-12 shrink-0 object-contain">
                <span class="ml-2 min-w-0 truncate text-sm font-semibold leading-tight text-slate-900">{{ $libraryName }}</span>
            </a>
            <button type="button" data-sidebar-close aria-label="Fechar menu" class="grid h-11 w-11 place-items-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden">
                <x-shared.icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div class="space-y-0.5">
                @can('dashboard.view')<x-layout.nav-link route="dashboard" icon="home" :active="request()->routeIs('dashboard')">Início</x-layout.nav-link>@endcan
                @can('catalog.view')<x-layout.nav-link route="books.index" icon="book" :active="request()->routeIs('books.index', 'books.show', 'books.edit', 'books.create')">Catálogo</x-layout.nav-link>@endcan
                @can('catalog.create')<x-layout.nav-link route="books.scanner" icon="scan" :active="request()->routeIs('books.scanner')">Escanear livro</x-layout.nav-link>@endcan
                @can('copy.view')<x-layout.nav-link route="copies.index" icon="stack" :active="request()->routeIs('copies.*')">Acervo</x-layout.nav-link>@endcan
                @can('borrower.view')<x-layout.nav-link route="borrowers.index" icon="users" :active="request()->routeIs('borrowers.*')">Leitores</x-layout.nav-link>@endcan
            </div>

            @canany(['loan.create', 'loan.view', 'reservation.view'])
                <div>
                    <p class="eyebrow px-3 pb-2">Circulação</p>
                    <div class="space-y-0.5">
                        @can('loan.create')<x-layout.nav-link route="circulation.desk" icon="swap" :active="request()->routeIs('circulation.*')">Balcão</x-layout.nav-link>@endcan
                        @can('loan.view')<x-layout.nav-link route="loans.index" icon="list" :active="request()->routeIs('loans.*')">Empréstimos</x-layout.nav-link>@endcan
                        @can('reservation.view')<x-layout.nav-link route="reservations.index" icon="bookmark" :active="request()->routeIs('reservations.*')">Reservas</x-layout.nav-link>@endcan
                    </div>
                </div>
            @endcanany

            @can('report.view')
                <div class="space-y-0.5">
                    <x-layout.nav-link route="reports.index" icon="chart" :active="request()->routeIs('reports.*')">Relatórios</x-layout.nav-link>
                </div>
            @endcan

            @canany(['settings.manage', 'user.manage', 'category.manage', 'location.manage', 'audit.view'])
                <div>
                    <p class="eyebrow px-3 pb-2">Administração</p>
                    <div class="space-y-0.5">
                        @can('category.manage')<x-layout.nav-link route="categories.index" icon="tag" :active="request()->routeIs('categories.*')">Disciplinas e categorias</x-layout.nav-link>@endcan
                        @can('location.manage')<x-layout.nav-link route="locations.index" icon="pin" :active="request()->routeIs('locations.*')">Localizações</x-layout.nav-link>@endcan
                        @can('settings.manage')<x-layout.nav-link route="loan-rules.edit" icon="rules" :active="request()->routeIs('loan-rules.*')">Regras de empréstimo</x-layout.nav-link>@endcan
                        @can('settings.manage')<x-layout.nav-link route="settings.edit" icon="cog" :active="request()->routeIs('settings.*')">Configurações</x-layout.nav-link>@endcan
                        @can('user.manage')<x-layout.nav-link route="users.index" icon="shield" :active="request()->routeIs('users.*')">Usuários</x-layout.nav-link>@endcan
                        @can('audit.view')<x-layout.nav-link route="audit.index" icon="clock" :active="request()->routeIs('audit.*')">Auditoria</x-layout.nav-link>@endcan
                    </div>
                </div>
            @endcanany
        </nav>

        <div class="border-t border-slate-200 p-3 safe-bottom">
            <div class="px-2 pb-2">
                <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                <p class="text-xs text-slate-500">{{ $user->role->label() }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('account.password') }}" class="btn btn-secondary flex-1 !min-h-11 !text-xs">Minha senha</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full !min-h-11 !text-xs">Sair</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        <main id="conteudo" class="mx-auto w-full max-w-7xl px-4 pb-28 pt-6 sm:px-6 lg:px-12 lg:pb-12 lg:pt-10">
            <x-ui.error-summary />
            @yield('content')
        </main>
    </div>

    {{-- Navegação inferior (celular): as quatro áreas mais usadas + "Mais" --}}
    <nav data-bottom-nav aria-label="Atalhos" class="bottom-nav fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 lg:hidden">
        <ul class="mx-auto flex max-w-lg items-stretch justify-around">
            @foreach ($mobileItems as [$route, $icon, $label, $active])
                <li class="flex-1">
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                       class="relative flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium {{ $active ? 'text-indigo-700' : 'text-slate-500' }}">
                        @if ($active)<span class="absolute inset-x-5 top-0 h-0.5 bg-indigo-700"></span>@endif
                        <x-shared.icon :name="$icon" class="h-6 w-6" />
                        {{ $label }}
                    </a>
                </li>
            @endforeach
            <li class="flex-1">
                <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false"
                        class="flex h-16 w-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium text-slate-500">
                    <x-shared.icon name="menu" class="h-6 w-6" />
                    Mais
                </button>
            </li>
        </ul>
    </nav>

    <x-ui.feedback />
    @stack('scripts')
</body>
</html>
