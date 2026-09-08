<?php

namespace App\Services\Ocr\Rasterizers;

use App\Services\Ocr\BinaryFinder;
use RuntimeException;
use Symfony\Component\Process\Process;

class MuPdfRasterizer implements PdfRasterizer
{
    private ?string $bin;

    public function __construct()
    {
        $this->bin = BinaryFinder::find(config('ocr.mutool_path'), ['mutool'], [
            'C:\\Program Files\\mupdf\\mutool.exe',
            (getenv('LOCALAPPDATA') ?: '') . '\\Microsoft\\WinGet\\Packages\\ArtifexSoftware.mutool_*\\mupdf-*\\mutool.exe',
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->bin !== null;
    }

    public function name(): string
    {
        return 'mutool';
    }

    public function rasterize(string $pdfPath, string $outDir, int $dpi, ?int $maxPages = null): array
    {
        $pattern = $outDir . DIRECTORY_SEPARATOR . 'page-%04d.png';

        $args = [$this->bin, 'draw', '-r', (string) $dpi, '-o', $pattern, $pdfPath];

        // mutool takes a trailing page range ("1-60"); without it every page
        // is rendered even when only the first few are wanted.
        if ($maxPages !== null && $maxPages > 0) {
            $args[] = '1-' . $maxPages;
        }

        $process = new Process($args);
        $process->setTimeout(config('ocr.timeout'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('mutool rasterization failed: ' . $process->getErrorOutput());
        }

        $images = glob($outDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        sort($images);
        return $images;
    }
}
