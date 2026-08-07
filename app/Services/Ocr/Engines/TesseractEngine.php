<?php

namespace App\Services\Ocr\Engines;

use App\Services\Ocr\BinaryFinder;
use RuntimeException;
use Symfony\Component\Process\Process;

class TesseractEngine implements OcrEngine
{
    private ?string $bin;

    public function __construct()
    {
        $this->bin = BinaryFinder::find(config('ocr.tesseract_path'), ['tesseract'], [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->bin !== null;
    }

    public function name(): string
    {
        return 'tesseract';
    }

    public function recognize(string $imagePath): string
    {
        $outputBase = $imagePath . '.out'; // tesseract appends .txt itself

        $process = new Process([$this->bin, $imagePath, $outputBase, '-l', config('ocr.language')]);
        $process->setTimeout(config('ocr.timeout'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Tesseract OCR failed: ' . $process->getErrorOutput());
        }

        $textFile = $outputBase . '.txt';
        if (!is_file($textFile)) {
            throw new RuntimeException("Tesseract produced no output for: {$imagePath}");
        }

        return trim(file_get_contents($textFile));
    }
}
