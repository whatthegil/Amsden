<?php

namespace App\Services\Ocr\Rasterizers;

use Symfony\Component\Process\ExecutableFinder;

/**
 * Runs a rasterizer at the lowest CPU priority where the host allows it.
 *
 * On Laravel Cloud, rendering a thesis happens on the same small instance that
 * answers readers, and for minutes at a time. At normal priority the renderer
 * competes with every page request; under `nice -n 19` the kernel gives it
 * only what readers leave spare. The render takes a little longer, and the
 * site stays responsive while it runs. Nothing changes on Windows, which has
 * no nice.
 */
trait LowPriority
{
    /** @param string[] $args */
    private function lowPriority(array $args): array
    {
        if (!config('ocr.low_priority', true) || PHP_OS_FAMILY === 'Windows') {
            return $args;
        }

        static $nice = false;
        if ($nice === false) {
            $nice = (new ExecutableFinder())->find('nice');
        }

        return $nice ? array_merge([$nice, '-n', '19'], $args) : $args;
    }
}
