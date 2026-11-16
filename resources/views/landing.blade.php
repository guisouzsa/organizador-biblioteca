<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f5f5">
    <meta name="description" content="Sistema de biblioteca escolar: catálogo, exemplares com código de barras, leitores, empréstimos, devoluções e reservas em um só lugar.">
    <title>{{ config('app.name') }} — gestão de biblioteca escolar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('decor/efeitos.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('decor/logo.png') }}">
</head>
<body class="font-sans antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Ir para o conteúdo</a>

    <header class="sticky top-0 z-30 border-b border-cinza-5 bg-white/95" style="padding-top: env(safe-area-inset-top)">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-2.5">
                <img src="{{ asset('decor/logo.png') }}" alt="{{ config('app.name') }} — início" width="120" height="100" class="logo-mark h-10 w-auto">
                <span class="flex flex-col">
                    <span class="text-sm font-semibold tracking-tight text-slate-900 leading-tight">{{ config('app.name') }}</span>
                    <span class="text-[0.625rem] font-medium uppercase tracking-wider text-slate-500 leading-tight">Biblioteca Escolar</span>
                </span>
            </a>
            <nav aria-label="Seções" class="hidden items-center gap-6 text-sm font-medium text-slate-700 md:flex">
                <a href="#recursos" class="link-ondulado">Recursos</a>
                <a href="#fluxo" class="link-ondulado">Como funciona</a>
                <a href="#celular" class="link-ondulado">No celular</a>
                <a href="#perguntas" class="link-ondulado">Perguntas</a>
            </nav>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Abrir painel</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Entrar</a>
            @endauth
        </div>
    </header>

    <main id="conteudo">
        {{-- Abertura --}}
        <section class="fundo-patas border-b border-cinza-5">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:px-10 lg:py-24">
                <div>
                    <p class="eyebrow">Biblioteca escolar</p>
                    <h1 class="mt-3 text-4xl leading-[1.08] sm:text-5xl lg:text-6xl">Do catálogo ao balcão, tudo em ordem.</h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-700 sm:text-lg">
                        Um sistema para a biblioteca da escola cadastrar livros, controlar cada exemplar, atender leitores e registrar
                        empréstimos, devoluções e reservas, sem planilhas e sem fichas soltas.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">Abrir painel</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Entrar no sistema</a>
                        @endauth
                        <a href="#recursos" class="btn btn-secondary btn-lg">Conhecer os recursos</a>
                    </div>
                    <p class="mt-4 text-xs text-slate-600">O acesso é feito por contas criadas pela própria biblioteca. Não há cadastro público.</p>
                </div>

                {{-- Ficha catalográfica ilustrativa (sem dados reais) --}}
                <div class="espia hidden lg:block" aria-hidden="true">
                    <x-shared.gato nome="espiando" class="espia__gato" />
                    <div class="panel p-6">
                        <p class="eyebrow">Ficha do exemplar</p>
                        <p class="mt-2 text-xl font-semibold leading-snug">Título do livro</p>
                        <p class="text-sm text-slate-600">Autor · Editora · Ano</p>
                        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-cinza-5 pt-4 text-sm">
                            <div><dt class="eyebrow">Código</dt><dd class="catalog-id mt-0.5 !text-sm !text-slate-900">EX-000123</dd></div>
                            <div><dt class="eyebrow">Localização</dt><dd class="mt-0.5">Estante A · Prateleira 2</dd></div>
                            <div><dt class="eyebrow">Estado</dt><dd class="mt-0.5">Em circulação</dd></div>
                            <div><dt class="eyebrow">Situação</dt><dd class="mt-0.5">Disponível</dd></div>
                        </dl>
                        <p class="mt-4 text-[0.6875rem] text-slate-500">Exemplo ilustrativo do tipo de informação que o sistema organiza.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Recursos --}}
        <section id="recursos" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20 lg:px-10">
            <p class="eyebrow">O que o sistema faz</p>
            <h2 class="mt-2 max-w-2xl text-3xl sm:text-4xl">Cada parte da rotina da biblioteca, no lugar certo.</h2>

            <ol class="mt-10 grid gap-x-12 sm:grid-cols-2">
                @foreach ([
                    ['Catálogo bibliográfico', 'Cadastre livros por ISBN: o sistema consulta Open Library e Google Books, mostra a capa e os dados encontrados e você confere tudo antes de salvar. Também aceita cadastro manual, inclusive de livros sem ISBN.'],
                    ['Exemplares e etiquetas', 'Cada cópia física tem seu código de barras. Gere etiquetas para imprimir, informe a localização na estante e acompanhe o estado de conservação e o histórico de cada exemplar.'],
                    ['Leitores', 'Cadastro de alunos, professores e funcionários, com busca por nome ou matrícula, empréstimos ativos e histórico. É possível remover dados pessoais mantendo o histórico, em respeito à LGPD.'],
                    ['Balcão de circulação', 'Empréstimo e devolução com leitura do código pela câmera ou digitação. Cada operação é gravada por inteiro ou não é gravada, e o mesmo exemplar nunca fica emprestado para duas pessoas ao mesmo tempo.'],
                    ['Reservas', 'Fila por ordem de chegada. Quando o exemplar volta, ele é separado para o primeiro da fila e a reserva expira se não for retirada no prazo definido pela escola.'],
                    ['Painel e relatórios', 'Números reais do acervo e da circulação, lista de atrasos e reservas prontas para retirada. Relatórios podem ser exportados em CSV.'],
                ] as $i => [$title, $text])
                    <li class="border-t border-cinza-5 py-6">
                        <span class="catalog-id">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="mt-1 text-lg font-semibold">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-700">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <hr class="divisor-patas mx-auto max-w-6xl px-4" aria-hidden="true">

        {{-- Fluxo --}}
        <section id="fluxo" class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-16 lg:px-10">
            <p class="eyebrow">Como funciona</p>
            <h2 class="mt-2 max-w-2xl text-3xl sm:text-4xl">Quatro passos do primeiro livro ao primeiro empréstimo.</h2>
            <ol class="mt-10 grid gap-6 md:grid-cols-4">
                @foreach ([
                    ['Cadastre o livro', 'Escaneie o ISBN ou preencha os dados. Revise e confirme.'],
                    ['Registre os exemplares', 'Informe quantas cópias a escola tem e imprima as etiquetas com código de barras.'],
                    ['Cadastre os leitores', 'Alunos, professores e funcionários, com matrícula e turma.'],
                    ['Empreste e receba', 'No balcão, leia o código do exemplar e confirme. A devolução é igualmente rápida.'],
                ] as $i => [$title, $text])
                    <li class="border-t-2 border-slate-900 pt-4">
                        <span class="text-3xl font-semibold tabular-nums text-cinza-1" aria-hidden="true">{{ $i + 1 }}</span>
                        <h3 class="mt-2 text-base font-semibold">{{ $title }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-700">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-10 max-w-3xl">
                <x-ui.alert tone="info" title="As regras são da escola, não do sistema">
                    Prazo de empréstimo, limite de livros por leitor, renovações e o que fazer com atrasos são definidos por um administrador.
                    Enquanto essas regras não forem configuradas, o sistema bloqueia novos empréstimos e explica o motivo, em vez de inventar valores.
                </x-ui.alert>
            </div>
        </section>

        {{-- Celular --}}
        <section id="celular" class="border-y border-cinza-5 bg-white">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-2 lg:items-center lg:px-10">
                <div>
                    <p class="eyebrow">No celular</p>
                    <h2 class="mt-2 text-3xl sm:text-4xl">Feito para usar em pé, ao lado da estante.</h2>
                    <p class="mt-4 max-w-lg text-base leading-relaxed text-slate-700">
                        A interface tem um modo próprio para telas pequenas: navegação inferior ao alcance do polegar, botões grandes,
                        formulários de uma coluna e listas no lugar de tabelas. A câmera do celular lê o código de barras do livro
                        e do exemplar.
                    </p>
                </div>
                <ul class="space-y-4 text-sm text-slate-700">
                    <li class="flex gap-3 border-t border-cinza-5 pt-4"><x-shared.icon name="scan" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /><span><strong class="text-slate-900">Leitura por câmera.</strong> Se a leitura falhar duas vezes, o sistema oferece tentar de novo ou cadastrar manualmente.</span></li>
                    <li class="flex gap-3 border-t border-cinza-5 pt-4"><x-shared.icon name="swap" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /><span><strong class="text-slate-900">Balcão rápido.</strong> Identifique o leitor, leia o exemplar e confirme em poucos toques.</span></li>
                    <li class="flex gap-3 border-t border-cinza-5 pt-4"><x-shared.icon name="search" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /><span><strong class="text-slate-900">Busca à mão.</strong> Pesquisa do catálogo a um toque, em qualquer tela.</span></li>
                </ul>
            </div>
        </section>

        {{-- Perfis e segurança --}}
        <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20 lg:px-10">
            <div class="grid gap-12 lg:grid-cols-2">
                <div>
                    <p class="eyebrow">Perfis de acesso</p>
                    <h2 class="mt-2 text-3xl">Cada pessoa vê e faz o que o seu papel permite.</h2>
                    <dl class="mt-6 divide-y divide-cinza-5 border-y border-cinza-5 text-sm">
                        <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr]"><dt class="font-semibold">Administrador</dt><dd class="text-slate-700">Configura regras, usuários, categorias, localizações e consulta a auditoria.</dd></div>
                        <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr]"><dt class="font-semibold">Bibliotecário</dt><dd class="text-slate-700">Cuida do catálogo, dos exemplares, dos leitores e da circulação.</dd></div>
                        <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr]"><dt class="font-semibold">Auxiliar</dt><dd class="text-slate-700">Atende o balcão: empréstimos, devoluções e reservas.</dd></div>
                        <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr]"><dt class="font-semibold">Consulta</dt><dd class="text-slate-700">Pesquisa o catálogo, sem alterar nada.</dd></div>
                    </dl>
                </div>
                <div>
                    <p class="eyebrow">Segurança e privacidade</p>
                    <h2 class="mt-2 text-3xl">Rastreável e cuidadoso com dados pessoais.</h2>
                    <ul class="mt-6 space-y-3 text-sm leading-relaxed text-slate-700">
                        <li class="flex gap-3"><x-shared.icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /> Operações importantes ficam registradas em um histórico de auditoria, com quem fez e quando.</li>
                        <li class="flex gap-3"><x-shared.icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /> Senhas protegidas, bloqueio por excesso de tentativas e troca de senha obrigatória no primeiro acesso.</li>
                        <li class="flex gap-3"><x-shared.icon name="users" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" /> Dados de contato dos leitores só aparecem para quem tem permissão e podem ser removidos, preservando o histórico.</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Perguntas --}}
        <section id="perguntas" class="border-t border-cinza-5 bg-white">
            <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20">
                <p class="eyebrow">Perguntas frequentes</p>
                <h2 class="mt-2 text-3xl">O que costuma ser perguntado.</h2>
                <div class="mt-8 divide-y divide-cinza-5 border-y border-cinza-5">
                    @foreach ([
                        ['Preciso de código de barras nos livros?', 'Para os exemplares, sim: o sistema gera as etiquetas para você imprimir e colar. O ISBN do verso do livro serve apenas para identificá-lo no catálogo.'],
                        ['E se o livro não tiver ISBN?', 'Dá para cadastrar manualmente, sem ISBN. O sistema avisa se já existir um livro parecido, sem impedir o cadastro.'],
                        ['De onde vêm os dados dos livros?', 'De fontes públicas (Open Library e Google Books), sempre mostrando a origem. Nada é salvo sem a sua conferência e você pode corrigir qualquer campo.'],
                        ['O sistema define prazos e multas?', 'Não. Prazos, limites e tratamento de atrasos são configurados por um administrador conforme a política da escola.'],
                        ['Posso usar no celular?', 'Sim. A interface foi pensada para telas pequenas e a câmera do aparelho lê os códigos de barras. Em navegadores, a câmera exige conexão segura (HTTPS) ou uso local.'],
                    ] as [$q, $a])
                        <details class="group py-1">
                            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 text-base font-medium [&::-webkit-details-marker]:hidden">
                                {{ $q }}
                                <x-shared.icon name="chevron" class="h-5 w-5 shrink-0 text-slate-600 transition group-open:rotate-180" />
                            </summary>
                            <p class="pb-4 pr-8 text-sm leading-relaxed text-slate-700">{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Chamada final --}}
        <section class="mx-auto max-w-6xl px-4 py-14 text-center sm:px-6 sm:py-20 lg:px-10">
            <x-shared.gato nome="dormindo" class="mx-auto h-24 w-auto" />
            <h2 class="mx-auto mt-4 max-w-xl text-3xl sm:text-4xl">Pronto para organizar a biblioteca?</h2>
            <p class="mx-auto mt-3 max-w-md text-sm text-slate-700">Entre com a conta fornecida pela biblioteca da sua escola.</p>
            <div class="mt-6">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">Abrir painel</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Entrar no sistema</a>
                @endauth
            </div>
        </section>
    </main>

    <footer class="border-t border-cinza-5 px-4 py-8 text-center text-xs text-slate-600" style="padding-bottom: calc(2rem + env(safe-area-inset-bottom))">
        <div class="inline-flex items-center justify-center gap-2 mb-2">
            <img src="{{ asset('decor/logo.png') }}" alt="" aria-hidden="true" width="96" height="80" class="logo-mark h-10 w-auto opacity-90" loading="lazy">
            <span class="text-sm font-semibold text-slate-800">{{ config('app.name') }}</span>
        </div>
        Sistema de gestão de biblioteca escolar
    </footer>

    <x-ui.feedback />
</body>
</html>
