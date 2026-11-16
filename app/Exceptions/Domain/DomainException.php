<?php

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use RuntimeException;

/**
 * Violação de regra de negócio, com código estável e mensagem segura para o usuário.
 * Nunca carrega dados técnicos ou sensíveis.
 */
class DomainException extends RuntimeException
{
    /** @param  array<string, mixed>  $context  dados não sensíveis para a interface (ex.: id do registro existente) */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $userMessage = null,
        public readonly array $context = [],
    ) {
        parent::__construct($userMessage ?? $errorCode->defaultMessage());
    }

    public static function of(ErrorCode $code, ?string $message = null, array $context = []): self
    {
        return new self($code, $message, $context);
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function code(): string
    {
        return $this->errorCode->value;
    }
}
