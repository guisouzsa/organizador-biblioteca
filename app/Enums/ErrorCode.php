<?php

namespace App\Enums;

/** Códigos estáveis de erro de negócio (catálogo em docs/arquitetura/08-erros-permissoes.md). */
enum ErrorCode: string
{
    case ConflitoEdicao = 'CONFLITO_EDICAO';
    case LivroArquivado = 'LIVRO_ARQUIVADO';
    case LivroSemExemplares = 'LIVRO_SEM_EXEMPLARES';
    case ExemplarNaoEncontrado = 'EXEMPLAR_NAO_ENCONTRADO';
    case ExemplarDuplicado = 'EXEMPLAR_DUPLICADO';
    case ExemplarIndisponivel = 'EXEMPLAR_INDISPONIVEL';
    case ExemplarJaEmprestado = 'EXEMPLAR_JA_EMPRESTADO';
    case ExemplarRetidoOutro = 'EXEMPLAR_RETIDO_OUTRO';
    case TransicaoInvalida = 'TRANSICAO_INVALIDA';
    case LeitorNaoEncontrado = 'LEITOR_NAO_ENCONTRADO';
    case LeitorInativo = 'LEITOR_INATIVO';
    case LeitorComAtraso = 'LEITOR_COM_ATRASO';
    case LimiteEmprestimos = 'LIMITE_EMPRESTIMOS';
    case RegrasNaoConfiguradas = 'REGRAS_NAO_CONFIGURADAS';
    case RenovacaoNegada = 'RENOVACAO_NEGADA';
    case ReservaDuplicada = 'RESERVA_DUPLICADA';
    case ReservaDesnecessaria = 'RESERVA_DESNECESSARIA';
    case DevolucaoSemEmprestimo = 'DEVOLUCAO_SEM_EMPRESTIMO';
    case MatriculaDuplicada = 'MATRICULA_DUPLICADA';
    case CategoriaDuplicada = 'CATEGORIA_DUPLICADA';
    case EmUso = 'EM_USO';
    case UltimoAdmin = 'ULTIMO_ADMIN';
    case OperacaoInvalida = 'OPERACAO_INVALIDA';

    public function defaultMessage(): string
    {
        return match ($this) {
            self::ConflitoEdicao => 'Este registro foi alterado por outra pessoa. Recarregue a página e revise as diferenças.',
            self::LivroArquivado => 'Este livro está arquivado.',
            self::LivroSemExemplares => 'Este livro ainda não tem exemplares.',
            self::ExemplarNaoEncontrado => 'Código não encontrado. Confira o número ou busque o livro.',
            self::ExemplarDuplicado => 'Este código ou tombo já pertence a outro exemplar.',
            self::ExemplarIndisponivel => 'Este exemplar não está disponível para empréstimo.',
            self::ExemplarJaEmprestado => 'Este exemplar já está emprestado.',
            self::ExemplarRetidoOutro => 'Este exemplar está reservado para outro leitor.',
            self::TransicaoInvalida => 'Esta mudança de situação não é permitida.',
            self::LeitorNaoEncontrado => 'Nenhum leitor encontrado.',
            self::LeitorInativo => 'Este leitor não está ativo.',
            self::LeitorComAtraso => 'Este leitor tem empréstimos em atraso.',
            self::LimiteEmprestimos => 'O limite de empréstimos deste leitor foi atingido.',
            self::RegrasNaoConfiguradas => 'As regras de empréstimo ainda não foram configuradas para este tipo de leitor. Peça a um administrador que as defina.',
            self::RenovacaoNegada => 'Não é possível renovar este empréstimo.',
            self::ReservaDuplicada => 'Este leitor já tem uma reserva ativa deste livro.',
            self::ReservaDesnecessaria => 'Há exemplar disponível: registre o empréstimo em vez de reservar.',
            self::DevolucaoSemEmprestimo => 'Este exemplar não consta como emprestado.',
            self::MatriculaDuplicada => 'Já existe um leitor com esta matrícula.',
            self::CategoriaDuplicada => 'Já existe uma categoria com este nome.',
            self::EmUso => 'Este item está em uso. Arquive-o em vez de excluir.',
            self::UltimoAdmin => 'É preciso manter ao menos um administrador ativo.',
            self::OperacaoInvalida => 'Não foi possível concluir esta operação.',
        };
    }
}
