<?php

namespace App\Services;

/**
 * Validação, normalização e conversão de ISBN-10 / ISBN-13.
 *
 * Um EAN-13 só é tratado como ISBN quando começa com 978 ou 979
 * e possui dígito verificador válido.
 */
class IsbnValidatorService
{
    /**
     * Remove prefixo "ISBN", espaços e hifens; mantém dígitos e "X" final.
     */
    public function normalize(?string $input): string
    {
        $value = strtoupper(trim((string) $input));
        $value = preg_replace('/^ISBN(?:-1[03])?:?/', '', $value) ?? '';

        return preg_replace('/[\s\-\x{2010}-\x{2015}]/u', '', $value) ?? '';
    }

    public function isValidIsbn13(string $isbn): bool
    {
        if (! preg_match('/^(978|979)\d{10}$/', $isbn)) {
            return false;
        }

        return (int) $isbn[12] === $this->isbn13CheckDigit(substr($isbn, 0, 12));
    }

    public function isValidIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $sum += $digit * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    /** ISBN-10 válido → ISBN-13 (prefixo 978). */
    public function toIsbn13(string $isbn10): ?string
    {
        if (! $this->isValidIsbn10($isbn10)) {
            return null;
        }

        $base = '978'.substr($isbn10, 0, 9);

        return $base.$this->isbn13CheckDigit($base);
    }

    /** ISBN-13 com prefixo 978 → ISBN-10. O prefixo 979 não possui equivalente ISBN-10. */
    public function toIsbn10(string $isbn13): ?string
    {
        if (! $this->isValidIsbn13($isbn13) || ! str_starts_with($isbn13, '978')) {
            return null;
        }

        $base = substr($isbn13, 3, 9);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $base[$i] * (10 - $i);
        }
        $check = (11 - ($sum % 11)) % 11;

        return $base.($check === 10 ? 'X' : (string) $check);
    }

    /**
     * Interpreta uma entrada (leitura da câmera ou digitação).
     *
     * @return array{valid: bool, isbn13: ?string, isbn10: ?string, error: ?string}
     */
    public function parse(?string $input): array
    {
        $value = $this->normalize($input);

        if ($value === '') {
            return $this->result(null, null, 'Informe um ISBN.');
        }

        if (strlen($value) === 13 && ctype_digit($value)) {
            if (! str_starts_with($value, '978') && ! str_starts_with($value, '979')) {
                return $this->result(null, null, 'O código lido é um EAN-13, mas não é um ISBN (deve começar com 978 ou 979).');
            }
            if (! $this->isValidIsbn13($value)) {
                return $this->result(null, null, 'ISBN-13 inválido: o dígito verificador não confere.');
            }

            return $this->result($value, $this->toIsbn10($value), null);
        }

        if (strlen($value) === 10) {
            if (! $this->isValidIsbn10($value)) {
                return $this->result(null, null, 'ISBN-10 inválido: o dígito verificador não confere.');
            }

            return $this->result($this->toIsbn13($value), $value, null);
        }

        return $this->result(null, null, 'O ISBN deve ter 10 ou 13 caracteres.');
    }

    private function isbn13CheckDigit(string $first12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $first12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function result(?string $isbn13, ?string $isbn10, ?string $error): array
    {
        return ['valid' => $error === null, 'isbn13' => $isbn13, 'isbn10' => $isbn10, 'error' => $error];
    }
}
