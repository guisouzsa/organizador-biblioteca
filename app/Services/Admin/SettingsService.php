<?php

namespace App\Services\Admin;

use App\Models\LibrarySetting;
use Illuminate\Support\Facades\Auth;

/** Configurações da biblioteca persistidas no banco (chave/valor). */
class SettingsService
{
    public const LIBRARY_NAME = 'library.name';

    public const BARCODE_PREFIX = 'barcode.prefix';

    public const ACCESSION_REQUIRED = 'copy.accession_required';

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = LibrarySetting::find($key);

        return $setting ? ($setting->value['v'] ?? $default) : $default;
    }

    public function set(string $key, mixed $value): void
    {
        LibrarySetting::updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'updated_by' => Auth::id()]);
    }

    public function libraryName(): string
    {
        return (string) $this->get(self::LIBRARY_NAME, config('app.name'));
    }

    /** Prefixo técnico dos códigos de barras dos exemplares (não é política escolar). */
    public function barcodePrefix(): string
    {
        return (string) $this->get(self::BARCODE_PREFIX, 'EX');
    }

    public function accessionRequired(): bool
    {
        return (bool) $this->get(self::ACCESSION_REQUIRED, false);
    }
}
