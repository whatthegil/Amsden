<?php

namespace App\Services\Ocr\Engines;

interface OcrEngine
{
    public function isAvailable(): bool;

    public function name(): string;

    public function recognize(string $imagePath): string;
}
