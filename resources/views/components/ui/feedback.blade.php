{{--
    Sistema central de feedback (um por página, dentro dos layouts).
    - Diálogo único reutilizável (#ui-dialog): sucesso, informação, aviso, erro, acesso negado e confirmação.
    - Região de toasts (#toast-region).
    - Os dados vindos do servidor (flash) seguem em JSON e são exibidos por resources/js/ui/dialogs.js,
      sempre via textContent (nada de HTML vindo de fora é interpretado).

    Critério de uso:
      modal  → confirmação, decisão importante ou resultado que exige atenção;
      toast  → confirmação breve de operação simples (flash `status`);
      campo  → validação específica, junto ao campo;
      alerta → problema que precisa permanecer visível (<x-ui.alert>).
--}}
@php
    $payload = session('feedback')
        ?? (session('error') ? \App\Support\Feedback::forError(session('error_code'), session('error'), (array) session('error_context', [])) : null);
    $toast = $payload ? null : session('status');
@endphp

<dialog id="ui-dialog" class="modal" aria-labelledby="ui-dialog-title" aria-describedby="ui-dialog-message">
    <div class="modal__head">
        <span class="modal__icon" id="ui-dialog-icon" aria-hidden="true"></span>
        <h2 id="ui-dialog-title" class="modal__title"></h2>
    </div>
    <div class="modal__body">
        <p id="ui-dialog-message" class="modal__message"></p>
        <div id="ui-dialog-extra"></div>
    </div>
    <div class="modal__foot" id="ui-dialog-actions"></div>
</dialog>

<dialog id="form-dialog" class="form-dialog" aria-labelledby="form-dialog-title">
    <div class="form-dialog__head">
        <div>
            <p class="eyebrow">Formulário</p>
            <h2 id="form-dialog-title" class="form-dialog__title">Carregando…</h2>
        </div>
        <button type="button" class="form-dialog__close" data-form-dialog-close aria-label="Fechar formulário">×</button>
    </div>
    <div id="form-dialog-content" class="form-dialog__body" aria-live="polite"></div>
</dialog>

<template id="ui-icons">
    <span data-icon="success"><x-shared.icon name="check" class="h-5 w-5" /></span>
    <span data-icon="info"><x-shared.icon name="info" class="h-5 w-5" /></span>
    <span data-icon="warning"><x-shared.icon name="alert" class="h-5 w-5" /></span>
    <span data-icon="error"><x-shared.icon name="xcircle" class="h-5 w-5" /></span>
    <span data-icon="denied"><x-shared.icon name="lock" class="h-5 w-5" /></span>
    <span data-icon="confirm"><x-shared.icon name="help" class="h-5 w-5" /></span>
</template>

<div id="toast-region" aria-live="polite" aria-atomic="false"
     class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 lg:bottom-6"></div>

<script type="application/json" id="ui-flash">@json(['modal' => $payload, 'toast' => $toast])</script>

@if ($payload || $toast)
    <noscript>
        <div class="fixed inset-x-4 bottom-4 z-[60] rounded-lg border border-slate-300 bg-white p-4 text-sm shadow-lg" role="status">
            <p class="font-semibold">{{ $payload['title'] ?? 'Aviso' }}</p>
            <p>{{ $payload['message'] ?? $toast }}</p>
        </div>
    </noscript>
@endif
