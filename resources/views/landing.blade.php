<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#c4c4c4">
    <meta name="description" content="Organização de acervo, leitores e circulação para bibliotecas escolares.">
    <title>{{ config('app.name') }} — gestão de biblioteca escolar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="marketing-page font-sans antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-3">Ir para o conteúdo</a>
    <header class="marketing-header">
        <div class="marketing-shell marketing-header__inner">
            <a href="{{ route('home') }}" class="wordmark" aria-label="{{ config('app.name') }} — início">
                <span class="wordmark__mark" aria-hidden="true">OE</span><span>{{ config('app.name') }}</span>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="marketing-link-button">Abrir painel</a>
            @else
                <a href="{{ route('login') }}" class="marketing-link-button">Acessar sistema</a>
            @endauth
        </div>
    </header>
    <main id="conteudo">
        <section class="marketing-hero">
            <div class="marketing-shell marketing-hero__grid">
                <div class="marketing-hero__copy">
                    <p class="marketing-kicker">Gestão de biblioteca escolar</p>
                    <h1>Seu acervo organizado.<br>Seu atendimento mais simples.</h1>
                    <p class="marketing-lead">Livros, exemplares, leitores e circulação reunidos em uma rotina clara para a biblioteca.</p>
                    @auth
                        <a href="{{ route('dashboard') }}" class="marketing-primary">Abrir painel</a>
                    @else
                        <a href="{{ route('login') }}" class="marketing-primary">Entrar no sistema</a>
                    @endauth
                    <p class="marketing-access-note">Acesso exclusivo para contas fornecidas pela biblioteca.</p>
                </div>
                <div class="library-visual" aria-label="Representação abstrata de livros organizados em uma estante" role="img">
                    <div class="library-visual__label">Acervo escolar</div>
                    <div class="library-visual__shelf">
                        <span class="book-spine book-spine--1"></span><span class="book-spine book-spine--2"></span>
                        <span class="book-spine book-spine--3"></span><span class="book-spine book-spine--4"></span>
                        <span class="book-spine book-spine--5"></span>
                    </div>
                    <div class="library-visual__meta"><span>Catálogo</span><span>Exemplares</span><span>Circulação</span></div>
                </div>
            </div>
        </section>
        <section class="marketing-purpose" aria-labelledby="proposito">
            <div class="marketing-shell marketing-purpose__grid">
                <div>
                    <p class="marketing-kicker">Uma rotina integrada</p>
                    <h2 id="proposito">Da catalogação à devolução, cada registro no lugar certo.</h2>
                </div>
                <div class="marketing-purpose__text">
                    <p>O Organizador Escolar conecta o acervo físico às pessoas que o utilizam. A equipe acompanha disponibilidade, empréstimos, reservas e histórico sem depender de controles dispersos.</p>
                    <ul class="marketing-points" aria-label="Recursos principais">
                        <li>Catálogo e exemplares</li><li>Leitores e empréstimos</li><li>Reservas e relatórios</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
    <footer class="marketing-footer">
        <div class="marketing-shell marketing-footer__inner"><span>{{ config('app.name') }}</span><span>Gestão de biblioteca escolar</span></div>
    </footer>
    <x-ui.feedback />
</body>
</html>
