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

    public function rasterize(string $pdfPath, string $outDir, int $dpi): array
    {
        $prefix = $outDir . DIRECTORY_SEPARATOR . 'page';

        $process = new Process([$this->bin, '-png', '-r', (string) $dpi, $pdfPath, $prefix]);
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
