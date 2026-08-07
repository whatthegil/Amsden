<?php

namespace App\Services\Ocr\Engines;

use App\Services\Ocr\BinaryFinder;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Zero-install fallback: uses the OCR engine built into Windows 10/11
 * (Windows.Media.Ocr) via a small PowerShell helper script, for machines
 * that don't have Tesseract or any other third-party OCR tool installed.
 */
class WindowsOcrEngine implements OcrEngine
{
    private ?string $powershell;

    public function __construct()
    {
        $this->powershell = PHP_OS_FAMILY === 'Windows'
            ? BinaryFinder::find(null, ['powershell', 'pwsh'])
            : null;
    }

    public function isAvailable(): bool
    {
        return $this->powershell !== null;
    }

    public function name(): string
    {
        return 'windows-ocr';
    }

    public function recognize(string $imagePath): string
    {
        $script = base_path('scripts/windows-ocr.ps1');

        $process = new Process([
            $this->powershell, '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
            '-File', $script, $imagePath, self::toBcp47(config('ocr.language')),
        ]);
        $process->setTimeout(config('ocr.timeout'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Windows OCR failed: ' . $process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    /** Windows OCR expects BCP-47 tags ("en"), not Tesseract's ISO 639-2 codes ("eng"). */
    private static function toBcp47(string $tesseractLangCode): string
    {
        $map = ['eng' => 'en', 'fil' => 'fil', 'spa' => 'es', 'fra' => 'fr', 'deu' => 'de'];
        return $map[$tesseractLangCode] ?? 'en';
    }
}
