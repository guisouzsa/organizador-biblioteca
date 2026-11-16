<?php

namespace App\Support;

/**
 * Linguagem única de feedback da interface (apenas apresentação; não contém regra de negócio).
 *
 * Quatro padrões compartilham a mesma identidade visual:
 *  - modal   → decisão ou resultado que exige atenção (flash `feedback`, `error`);
 *  - toast   → confirmação breve de operação simples (flash `status`);
 *  - campo   → validação específica, junto ao campo (erros do formulário);
 *  - alerta  → problema que precisa permanecer visível (componente x-ui.alert).
 *
 * Tons: success | info | warning (impedimento de regra) | error (falha real) | denied (sem permissão).
 */
final class Feedback
{
    /** @param  list<array{label: string, href: string, variant?: string}>  $actions */
    public static function make(string $tone, string $title, string $message, array $actions = [], ?string $focus = null): array
    {
        return ['tone' => $tone, 'title' => $title, 'message' => $message, 'actions' => $actions, 'focus' => $focus];
    }

    /** @param  list<array{label: string, href: string, variant?: string}>  $actions */
    public static function success(string $title, string $message, array $actions = [], ?string $focus = null): array
    {
        return self::make('success', $title, $message, $actions, $focus);
    }

    /**
     * Traduz um código de erro de negócio em título e tom adequados: impedimentos de regra
     * são avisos (warning), não falhas técnicas.
     *
     * @return array{tone: string, title: string, message: string, actions: array, focus: ?string}
     */
    public static function forError(?string $code, string $message, array $context = []): array
    {
        $map = [
            'EXEMPLAR_INDISPONIVEL' => ['warning', 'Exemplar indisponível'],
            'EXEMPLAR_JA_EMPRESTADO' => ['warning', 'Exemplar já emprestado'],
            'EXEMPLAR_RETIDO_OUTRO' => ['warning', 'Exemplar reservado para outro leitor'],
            'EXEMPLAR_NAO_ENCONTRADO' => ['error', 'Código não encontrado'],
            'EXEMPLAR_DUPLICADO' => ['warning', 'Código já utilizado'],
            'LEITOR_INATIVO' => ['warning', 'Leitor inativo'],
            'LEITOR_COM_ATRASO' => ['warning', 'Leitor com atraso'],
            'LEITOR_NAO_ENCONTRADO' => ['error', 'Leitor não encontrado'],
            'LIMITE_EMPRESTIMOS' => ['warning', 'Limite de empréstimos atingido'],
            'REGRAS_NAO_CONFIGURADAS' => ['warning', 'Regras de empréstimo não configuradas'],
            'RENOVACAO_NEGADA' => ['warning', 'Renovação não permitida'],
            'RESERVA_DUPLICADA' => ['warning', 'Reserva já existente'],
            'RESERVA_DESNECESSARIA' => ['info', 'Há exemplar disponível'],
            'DEVOLUCAO_SEM_EMPRESTIMO' => ['warning', 'Exemplar não está emprestado'],
            'LIVRO_ARQUIVADO' => ['warning', 'Livro arquivado'],
            'LIVRO_SEM_EXEMPLARES' => ['warning', 'Livro sem exemplares'],
            'MATRICULA_DUPLICADA' => ['warning', 'Matrícula já cadastrada'],
            'CATEGORIA_DUPLICADA' => ['warning', 'Categoria já existe'],
            'EM_USO' => ['warning', 'Item em uso'],
            'ULTIMO_ADMIN' => ['warning', 'Operação não permitida'],
            'TRANSICAO_INVALIDA' => ['warning', 'Mudança não permitida'],
            'CONFLITO_EDICAO' => ['warning', 'Registro alterado por outra pessoa'],
        ];
        [$tone, $title] = $map[$code] ?? ['error', 'Não foi possível concluir'];

        $actions = [];
        if ($code === 'REGRAS_NAO_CONFIGURADAS' && auth()->user()?->can('settings.manage')) {
            $actions[] = ['label' => 'Configurar regras', 'href' => route('loan-rules.edit'), 'variant' => 'primary'];
        }
        if (! empty($context['book_id'])) {
            $actions[] = ['label' => 'Abrir livro', 'href' => route('books.show', $context['book_id']), 'variant' => 'secondary'];
        }
        if (! empty($context['copy_id'])) {
            $actions[] = ['label' => 'Abrir exemplar', 'href' => route('copies.show', $context['copy_id']), 'variant' => 'secondary'];
        }
        if (! empty($context['borrower_id'])) {
            $actions[] = ['label' => 'Abrir ficha do leitor', 'href' => route('borrowers.show', $context['borrower_id']), 'variant' => 'secondary'];
        }

        return self::make($tone, $title, $message, $actions);
    }
}
