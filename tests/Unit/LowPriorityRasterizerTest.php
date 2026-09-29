<?php

namespace Tests\Unit;

use App\Services\Ocr\Rasterizers\LowPriority;
use Tests\TestCase;

/** Renders run under `nice` where there is one, so readers are served first. */
class LowPriorityRasterizerTest extends TestCase
{
    private function wrap(array $args): array
    {
        $subject = new class {
            use LowPriority;
            public function run(array $a): array { return $this->lowPriority($a); }
        };

        return $subject->run($args);
    }

    public function test_the_command_is_run_under_nice_where_the_host_has_it(): void
    {
        $args = $this->wrap(['pdftoppm', '-png', 'in.pdf', 'out']);

        if (PHP_OS_FAMILY === 'Windows' || (new \Symfony\Component\Process\ExecutableFinder())->find('nice') === null) {
            $this->assertSame(['pdftoppm', '-png', 'in.pdf', 'out'], $args, 'Unchanged where there is no nice.');
        } else {
            $this->assertSame(['-n', '19', 'pdftoppm'], array_slice($args, 1, 3));
            $this->assertStringEndsWith('nice', $args[0]);
        }
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['ocr.low_priority' => false]);

        $this->assertSame(['pdftoppm', 'x'], $this->wrap(['pdftoppm', 'x']));
    }
}
