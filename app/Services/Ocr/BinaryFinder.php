<?php

namespace App\Services\Ocr;

use Symfony\Component\Process\ExecutableFinder;

class BinaryFinder
{
    /**
     * Resolve a usable executable path by trying, in order:
     *   1. An explicitly configured path/command (from .env), if it exists or resolves on PATH
     *   2. Known command names on PATH
     *   3. Common install-location glob patterns (Windows Program Files / WinGet layouts)
     */
    public static function find(?string $configured, array $commandNames = [], array $globPatterns = []): ?string
    {
        $finder = new ExecutableFinder();

        if ($configured) {
            if (self::isExecutableFile($configured)) return $configured;
            $found = $finder->find($configured, null, []);
            if ($found) return $found;
        }

        foreach ($commandNames as $name) {
            $found = $finder->find($name, null, []);
            if ($found) return $found;
        }

        foreach ($globPatterns as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                if (self::isExecutableFile($path)) return $path;
            }
        }

        return null;
    }

    private static function isExecutableFile(string $path): bool
    {
        return is_file($path);
    }
}
