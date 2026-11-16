<?php

namespace App\Services\Catalog;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OcrIdentificationService
{
    public function extract(UploadedFile $image): string
    {
        $binary = (string) config('services.ocr.binary', 'tesseract');
        $language = (string) config('services.ocr.language', 'por+eng');
        $path = $image->getRealPath();

        $process = new Process([$binary, $path, 'stdout', '-l', $language, '--psm', '6']);
        $process->setTimeout((int) config('services.ocr.timeout', 20));

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return '';
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $process->getOutput()) ?? '';
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));

        return mb_substr(implode(' ', array_slice($lines, 0, 6)), 0, 160);
    }
}
