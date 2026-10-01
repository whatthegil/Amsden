<?php

namespace App\Services\Pdf;

use App\Models\Bluebook;
use App\Services\Store;
use GdImage;

/**
 * A bluebook's pages as images, watermarked for the reader on the way out.
 *
 * Laravel Cloud has no MuPDF, so it cannot stamp a PDF: the file it sent was
 * the clean original, and the viewer's mark lived only on screen - anyone who
 * saved the response from the browser's network tab had an unmarked copy.
 *
 * Instead the worker (which does have MuPDF) renders each page to a JPEG once,
 * and PHP's own GD extension - on every host - draws the reader's email and the
 * CSPC crest into the pixels of each page as it is sent. The browser is never
 * given a clean page to keep, and a page outside a partial waiver is never
 * sent at all.
 */
class PageImages
{
    /**
     * Where page $n of bluebook $id is kept on the bluebook disk.
     *
     * Kept as WebP: about half the size of a JPEG of the same page, with text
     * as sharp. It is only stored that way - pages are sent as JPEG, because
     * WebP takes several times longer to encode and that happens per request.
     */
    public static function path(int $id, int $n): string
    {
        return "bluebook-pages/{$id}/{$n}.webp";
    }

    public static function directory(int $id): string
    {
        return "bluebook-pages/{$id}";
    }

    /** Can this host draw the mark? GD with FreeType, and the bundled font. */
    public static function canWatermark(): bool
    {
        return function_exists('imagettftext')
            && function_exists('imagecreatefromstring')
            && is_file(self::font());
    }

    /**
     * Are this bluebook's page images there, and of the file it holds now? A
     * re-uploaded document keeps its old images until the worker redraws them,
     * and those must not be served as the new one.
     */
    public static function ready(array $bluebook): bool
    {
        return config('watermark.page_images', true)
            && ($bluebook['pageImagesCount'] ?? 0) > 0
            && ($bluebook['pageImagesSource'] ?? null) === ($bluebook['filePath'] ?? '')
            && self::canWatermark();
    }

    /**
     * A short fingerprint of everything a served page depends on: the reader
     * (their email is drawn in), the file, and the waiver, withheld pages and
     * any access granted to the reader (which decide which thesis page reader
     * page n is). Put in the page address so a browser may
     * keep pages long, and a new reader on a shared computer, a replaced file or
     * an edited waiver all get fresh addresses instead of someone else's pages.
     */
    public static function version(array $bluebook, string $email): string
    {
        return substr(sha1(implode('|', [
            $email,
            $bluebook['pageImagesSource'] ?? '',
            $bluebook['pageImagesCount'] ?? 0,
            $bluebook['accessLevel'] ?? '',
            json_encode($bluebook['accessParts'] ?? []),
            $bluebook['withheldPages'] ?? '',
            !empty($bluebook['granted']) ? 'granted' : '',
        ])), 0, 12);
    }

    /**
     * The original page numbers a student may see, in order - see
     * Bluebook::readerPages. Empty for a restricted paper the reader has not
     * been granted, and for a partial one whose ranges name nothing usable:
     * failing closed, as the document route does.
     *
     * $bluebook['granted'] is whether this reader holds an approved access
     * request for it.
     *
     * @return int[]
     */
    public static function visiblePages(array $bluebook): array
    {
        return Bluebook::readerPages($bluebook, (int) ($bluebook['pageImagesCount'] ?? 0), (bool) ($bluebook['granted'] ?? false));
    }

    /**
     * The page with the reader's email and the crest drawn into it, as a JPEG.
     * The same mark pdf-viewer.js draws: a tile across the page at -22 degrees,
     * crest over email, multiplied in so the words beneath stay black.
     */
    public static function watermark(string $jpeg, string $email): string
    {
        $page = imagecreatefromstring($jpeg);
        if (!$page instanceof GdImage) {
            throw new \RuntimeException('The page image could not be read.');
        }

        $w = imagesx($page);
        $h = imagesy($page);

        $tile  = max(260, (int) round($w / 2.2));
        $size  = max(11, (int) round($tile / 24));
        $crest = (int) round($tile * 0.28);
        $angle = 22.0;
        $cos   = cos(deg2rad($angle));
        $sin   = sin(deg2rad($angle));

        // Local (u, v) in the mark's frame, rotated counter-clockwise on screen,
        // to page pixels about the tile centre (x, y). y runs down the page.
        $at = fn(float $x, float $y, float $u, float $v) => [
            $x + $u * $cos + $v * $sin,
            $y - $u * $sin + $v * $cos,
        ];

        $logo = self::crest($crest, (int) round(config('watermark.page_logo_opacity', 0.18) * 100), $angle);
        $font = self::font();
        $ink  = imagecolorallocatealpha($page, 0x0f, 0x23, 0x50,
            127 - (int) round(127 * config('watermark.page_text_opacity', 0.30)));

        // Centre the email on the tile's axis.
        $box   = imagettfbbox($size, 0, $font, $email);
        $textW = $box[2] - $box[0];
        $capH  = $size * 0.72;

        imagealphablending($page, true);
        imagelayereffect($page, IMG_EFFECT_MULTIPLY);

        for ($y = $tile / 2; $y < $h + $tile; $y += $tile) {
            for ($x = $tile / 2; $x < $w + $tile; $x += $tile) {
                if ($logo) {
                    // The crest sits centred above the line, as in the viewer.
                    [$cx, $cy] = $at($x, $y, 0, -$crest / 2 - $size * 0.4);
                    imagecopy($page, $logo,
                        (int) round($cx - imagesx($logo) / 2), (int) round($cy - imagesy($logo) / 2),
                        0, 0, imagesx($logo), imagesy($logo));
                }
                if ($email !== '') {
                    [$tx, $ty] = $at($x, $y, -$textW / 2, $size * 0.6 + $capH / 2);
                    imagettftext($page, $size, $angle, (int) round($tx), (int) round($ty), $ink, $font, $email);
                }
            }
        }

        ob_start();
        imagejpeg($page, null, (int) config('watermark.page_quality', 80));
        return (string) ob_get_clean();
    }

    /**
     * The crest at $px wide, faded to $percent opacity and turned to $angle,
     * with its transparency kept. Built once per size and reused for a page.
     */
    private static function crest(int $px, int $percent, float $angle): ?GdImage
    {
        // Fading it touches every pixel, which was most of the time a page took.
        // Every page of a document is the same width, so one prepared crest per
        // size serves them all: kept as a file, since each page is its own request.
        $cached = storage_path("framework/cache/crest-{$px}-{$percent}-" . (int) $angle . '.png');
        if (is_file($cached) && ($img = @imagecreatefrompng($cached)) instanceof GdImage) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            return $img;
        }

        $made = self::buildCrest($px, $percent, $angle);
        if ($made) {
            // Written aside and moved into place, so a request reading it at the
            // same moment never sees half a file.
            $tmp = $cached . '.' . bin2hex(random_bytes(4));
            if (@imagepng($made, $tmp)) {
                @rename($tmp, $cached);
            }
            @unlink($tmp);
        }

        return $made;
    }

    private static function buildCrest(int $px, int $percent, float $angle): ?GdImage
    {
        $src = @imagecreatefrompng(public_path('images/cspc-logo.png'));
        if (!$src instanceof GdImage) {
            return null;
        }

        $ph = (int) round($px * imagesy($src) / imagesx($src));
        $img = imagecreatetruecolor($px, $ph);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $px, $ph, imagesx($src), imagesy($src));

        // Fade every pixel: GD's alpha runs 0 (solid) to 127 (clear).
        $keep = max(0, min(100, $percent)) / 100;
        for ($y = 0; $y < $ph; $y++) {
            for ($x = 0; $x < $px; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7f;
                $faded = 127 - (int) round((127 - $a) * $keep);
                imagesetpixel($img, $x, $y, ($c & 0xffffff) | ($faded << 24));
            }
        }

        $turned = imagerotate($img, $angle, imagecolorallocatealpha($img, 0, 0, 0, 127));
        if ($turned instanceof GdImage) {
            imagealphablending($turned, false);
            imagesavealpha($turned, true);
            return $turned;
        }

        return $img;
    }

    public static function font(): string
    {
        return resource_path('fonts/Inter-Bold.ttf');
    }

    /** The bluebook disk, where both the PDFs and their page images live. */
    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return \Illuminate\Support\Facades\Storage::disk(Store::bluebookDisk());
    }
}
