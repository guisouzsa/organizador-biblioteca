<?php

namespace App\Services\Catalog\Concerns;

/**
 * Normalização defensiva de dados vindos de APIs externas: qualquer campo com
 * tipo inesperado vira vazio, nunca um valor adivinhado.
 */
trait SanitizesExternalData
{
    protected function text(mixed $value, int $max = 10000): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** @return list<string> */
    protected function textList(mixed $value, int $max = 255): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (($text = $this->text($item, $max)) !== null) {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    protected function positiveInt(mixed $value, int $max = 20000): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 && $value <= $max ? $value : null;
    }

    /** Aceita apenas http(s); descarta javascript:, data:, etc. */
    protected function httpUrl(mixed $value): ?string
    {
        $url = $this->text($value, 2048);
        if ($url === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    protected function yearFrom(mixed $date): ?int
    {
        $date = $this->text($date, 50);

        return $date !== null && preg_match('/(?<!\d)(\d{4})(?!\d)/', $date, $m) ? (int) $m[1] : null;
    }
}
