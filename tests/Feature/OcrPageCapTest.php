<?php

namespace Tests\Feature;

use App\Services\Ocr\Rasterizers\GhostscriptRasterizer;
use App\Services\Ocr\Rasterizers\MuPdfRasterizer;
use App\Services\Ocr\Rasterizers\PdfRasterizer;
use App\Services\Ocr\Rasterizers\PopplerRasterizer;
use App\Services\OcrService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression cover for the page cap. It used to be applied to the *result* of
 * rasterize(), so a 200-page bluebook was rendered in full at 300 DPI (~90s,
 * ~300MB) before 188 pages were thrown away — which blew OCR_TIMEOUT and made
 * the similarity checker's "capped hard" 12-page fallback a full-document job.
 */
class OcrPageCapTest extends TestCase
{
    public function test_rasterizer_contract_accepts_a_page_cap(): void
    {
        $method = new ReflectionMethod(PdfRasterizer::class, 'rasterize');

        $this->assertSame(
            ['pdfPath', 'outDir', 'dpi', 'maxPages'],
            array_map(fn($p) => $p->getName(), $method->getParameters()),
            'rasterize() must take the cap so pages beyond it are never rendered.'
        );
    }

    /** @dataProvider rasterizers */
    public function test_every_rasterizer_implements_the_capped_signature(string $class): void
    {
        $method = new ReflectionMethod($class, 'rasterize');

        $this->assertCount(4, $method->getParameters(), "{$class} must accept \$maxPages.");
        $this->assertSame('maxPages', $method->getParameters()[3]->getName());
    }

    public static function rasterizers(): array
    {
        return [
            'mutool'      => [MuPdfRasterizer::class],
            'ghostscript' => [GhostscriptRasterizer::class],
            'poppler'     => [PopplerRasterizer::class],
        ];
    }

    public function test_ocr_service_forwards_the_cap_into_the_rasterize_call(): void
    {
        $source = file_get_contents((new ReflectionClass(OcrService::class))->getFileName());

        $this->assertMatchesRegularExpression(
            '/->rasterize\(\s*\$absolutePdfPath\s*,\s*\$workDir\s*,\s*config\(\x27ocr\.dpi\x27\)\s*,\s*\$maxPages\s*\)/',
            $source,
            'OcrService must hand $maxPages to the rasterizer. Slicing the returned '
            . 'page list instead renders the whole document first — the original bug.'
        );

        // ...and must still resolve the cap before that call, not after it.
        $rasterizeAt = strpos($source, '->rasterize(');
        $resolveAt   = strpos($source, '$maxPages = $maxPages ??');
        $this->assertNotFalse($resolveAt);
        $this->assertLessThan($rasterizeAt, $resolveAt);
    }

    public function test_prune_orphaned_work_dirs_removes_only_stale_dirs(): void
    {
        $root = storage_path('app/ocr-tmp');
        @mkdir($root, 0777, true);

        $fresh = $root . DIRECTORY_SEPARATOR . 'ocr_fresh_' . uniqid();
        $stale = $root . DIRECTORY_SEPARATOR . 'ocr_stale_' . uniqid();
        mkdir($fresh);
        mkdir($stale);
        file_put_contents($stale . DIRECTORY_SEPARATOR . 'page-0001.png', 'x');
        touch($stale, time() - 7200);

        OcrService::pruneOrphanedWorkDirs(3600);

        $this->assertDirectoryExists($fresh, 'An in-flight run must not be swept.');
        $this->assertDirectoryDoesNotExist($stale, 'An abandoned run must be swept.');

        @rmdir($fresh);
    }
}
