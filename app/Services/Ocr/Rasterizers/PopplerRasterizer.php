<?php

namespace App\Services\Ocr\Rasterizers;

use App\Services\Ocr\BinaryFinder;
use RuntimeException;
use Symfony\Component\Process\Process;

class PopplerRasterizer implements PdfRasterizer
{
    private ?string $bin;

    public function __construct()
    {
        $this->bin = BinaryFinder::find(config('ocr.pdftoppm_path'), ['pdftoppm'], [
            'C:\\poppler*\\Library\\bin\\pdftoppm.exe',
            'C:\\Program Files\\poppler*\\bin\\pdftoppm.exe',
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->bin !== null;
    }

    public function name(): string
    {
        return 'poppler';
    }

    public function rasterize(string $pdfPath, string $outDir, int $dpi, ?int $maxPages = null): array
    {
        $prefix = $outDir . DIRECTORY_SEPARATOR . 'page';

        $args = [$this->bin, '-png', '-r', (string) $dpi];

        // -f/-l bound the render at the source rather than after the fact.
        if ($maxPages !== null && $maxPages > 0) {
            $args[] = '-f';
            $args[] = '1';
            $args[] = '-l';
            $args[] = (string) $maxPages;
        }

        $args[] = $pdfPath;
        $args[] = $prefix;

        $process = new Process($args);
        $process->setTimeout(config('ocr.timeout'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('pdftoppm rasterization failed: ' . $process->getErrorOutput());
        }

        $images = glob($prefix . '-*.png') ?: [];
        natsort($images);
        return array_values($images);
    }
}
