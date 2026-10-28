<?php

namespace App\Domain\PipeGame\Contracts;

/**
 * Interface ScorableInterface
 * Minh họa khả năng một Class có thể implements nhiều Interface cùng lúc (Slide 13-14 Chương 2).
 */
interface ScorableInterface
{
    public function calculateFlowScore(string $difficulty = 'easy'): int;

    public function getPipeSummary(): array;
}
