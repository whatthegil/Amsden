<?php

namespace App\Services\Ocr\Rasterizers;

use App\Services\Ocr\BinaryFinder;
use RuntimeException;
use Symfony\Component\Process\Process;

class GhostscriptRasterizer implements PdfRasterizer
{
    private ?string $bin;

    public function __construct()
    {
        $this->bin = BinaryFinder::find(config('ocr.ghostscript_path'), ['gswin64c', 'gswin32c', 'gs'], [
            'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->bin !== null;
    }

    public function name(): string
    {
        return 'ghostscript';
    }

    public function rasterize(string $pdfPath, string $outDir, int $dpi): array
    {
        $pattern = $outDir . DIRECTORY_SEPARATOR . 'page-%04d.png';

        $process = new Process([
            $this->bin, '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=png16m', "-r{$dpi}", "-sOutputFile={$pattern}", $pdfPath,
        ]);
        $process->setTimeout(config('ocr.timeout'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Ghostscript rasterization failed: ' . $process->getErrorOutput());
        }

        $images = glob($outDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        sort($images);
        return $images;
    }
}
