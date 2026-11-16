<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Ir para o conteúdo</a>

    @php($user = auth()->user())

    {{-- Barra superior (celular/tablet) --}}
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-2 lg:hidden">
        <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false"
                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
            <x-shared.icon name="menu" class="h-5 w-5" /> Menu
        </button>
        <span class="font-semibold tracking-tight">{{ app(\App\Services\Admin\SettingsService::class)->libraryName() }}</span>
    </header>
    <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

    <aside id="sidebar" aria-label="Navegação principal"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 data-[open=true]:translate-x-0 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-indigo-600 text-white"><x-shared.icon name="book" class="h-5 w-5" /></span>
            <span class="font-semibold leading-tight tracking-tight">{{ app(\App\Services\Admin\SettingsService::class)->libraryName() }}</span>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                @can('dashboard.view')<x-layout.nav-link route="dashboard" icon="home" :active="request()->routeIs('dashboard')">Painel</x-layout.nav-link>@endcan
                @can('catalog.view')<x-layout.nav-link route="books.index" icon="book" :active="request()->routeIs('books.index', 'books.show', 'books.edit', 'books.create')">Catálogo</x-layout.nav-link>@endcan
                @can('catalog.create')<x-layout.nav-link route="books.scanner" icon="scan" :active="request()->routeIs('books.scanner')">Escanear livro</x-layout.nav-link>@endcan
                @can('copy.view')<x-layout.nav-link route="copies.index" icon="stack" :active="request()->routeIs('copies.*')">Acervo (exemplares)</x-layout.nav-link>@endcan
                @can('borrower.view')<x-layout.nav-link route="borrowers.index" icon="users" :active="request()->routeIs('borrowers.*')">Leitores</x-layout.nav-link>@endcan
            </div>

            @canany(['loan.create', 'loan.view', 'reservation.view'])
                <div>
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Circulação</p>
                    <div class="space-y-1">
                        @can('loan.create')<x-layout.nav-link route="circulation.desk" icon="swap" :active="request()->routeIs('circulation.*')">Balcão</x-layout.nav-link>@endcan
                        @can('loan.view')<x-layout.nav-link route="loans.index" icon="list" :active="request()->routeIs('loans.*')">Empréstimos</x-layout.nav-link>@endcan
                        @can('reservation.view')<x-layout.nav-link route="reservations.index" icon="bookmark" :active="request()->routeIs('reservations.*')">Reservas</x-layout.nav-link>@endcan
                    </div>
                </div>
            @endcanany

            @can('report.view')
                <div class="space-y-1">
                    <x-layout.nav-link route="reports.index" icon="chart" :active="request()->routeIs('reports.*')">Relatórios</x-layout.nav-link>
                </div>
            @endcan

            @canany(['settings.manage', 'user.manage', 'category.manage', 'location.manage', 'audit.view'])
                <div>
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Administração</p>
                    <div class="space-y-1">
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

        <div class="border-t border-slate-200 p-3">
            <div class="px-2 pb-2">
                <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                <p class="text-xs text-slate-500">{{ $user->role->label() }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('account.password') }}" class="flex-1 rounded-md px-3 py-2 text-center text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Minha senha</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full rounded-md px-3 py-2 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Sair</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <main id="conteudo" class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-shared.flash />
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
