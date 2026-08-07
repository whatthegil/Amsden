<?php

namespace App\Services\Ocr\Engines;

use RuntimeException;

class EngineResolver
{
    public static function resolve(): OcrEngine
    {
        $candidates = [
            new TesseractEngine(),
            new WindowsOcrEngine(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate->isAvailable()) return $candidate;
        }

        throw new RuntimeException(
            'No OCR engine found. Install Tesseract OCR, or run on Windows 10/11 (its built-in OCR is used as a fallback).'
        );
    }
}
