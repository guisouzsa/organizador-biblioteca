<?php

namespace App\Services\Inventory;

use App\Models\BookCopy;
use App\Models\CopyBarcode;
use App\Services\Admin\SettingsService;

/**
 * Códigos de barras internos dos exemplares (Code 128).
 * O código é gerado pelo sistema, único globalmente e nunca reutilizado (RN-ACE-02/04).
 */
class CopyBarcodeService
{
    public function __construct(private SettingsService $settings) {}

    /** Código base: prefixo + id do exemplar (ex.: EX-000123). */
    public function baseCode(BookCopy $copy): string
    {
        return $this->settings->barcodePrefix().'-'.str_pad((string) $copy->id, 6, '0', STR_PAD_LEFT);
    }

    /** Emite o primeiro código (ativo) do exemplar. */
    public function issue(BookCopy $copy): CopyBarcode
    {
        return $copy->barcodes()->create(['code' => $this->baseCode($copy), 'active' => true]);
    }

    /** Gera nova etiqueta: o código atual vira histórico e continua resolvendo para o exemplar. */
    public function relabel(BookCopy $copy, string $reason): CopyBarcode
    {
        $revision = $copy->barcodes()->count() + 1;

        $copy->barcodes()->where('active', true)->update([
            'active' => false, 'replaced_at' => now(), 'replaced_reason' => $reason,
        ]);

        return $copy->barcodes()->create(['code' => $this->baseCode($copy).'-'.$revision, 'active' => true]);
    }

    /**
     * Resolve o que foi lido/digitado: código de barras (atual ou antigo) ou tombo.
     *
     * @return array{copy: BookCopy, barcode: ?CopyBarcode, stale: bool, via: string}|null
     */
    public function resolve(?string $input): ?array
    {
        $code = strtoupper(trim((string) $input));
        if ($code === '') {
            return null;
        }

        $barcode = CopyBarcode::with('copy')->where('code', $code)->first();
        if ($barcode) {
            return ['copy' => $barcode->copy, 'barcode' => $barcode, 'stale' => ! $barcode->active, 'via' => 'barcode'];
        }

        $copy = BookCopy::where('accession_number', trim((string) $input))->first();

        return $copy ? ['copy' => $copy, 'barcode' => null, 'stale' => false, 'via' => 'accession'] : null;
    }
}
