<?php

namespace App\Domain\PipeGame\Pipes;

/**
 * Class CrossPipe (Ống Chữ Thập 4 nhánh)
 * Kế thừa AbstractPipe và override calculateFlowScore() để phản ánh dòng chảy đa nhánh.
 */
class CrossPipe extends AbstractPipe
{
    public function __construct(int $row, int $col, int $rotation = 0, string $colorGroup = 'OngXanh')
    {
        parent::__construct($row, $col, $rotation, $colorGroup);
    }

    public function getPipeType(): string
    {
        return 'ChuThap';
    }

    public function getDefaultPorts(): array
    {
        // Mở cả 4 hướng: Trên (0), Phải (1), Dưới (2), Trái (3)
        return [0, 1, 2, 3];
    }

    public function getSvgAssetPath(): string
    {
        return "Images/svg/Ong/{$this->colorGroup}/{$this->colorGroup}-ChuThap.svg";
    }

    /**
     * Override (Polymorphism): Ống chữ thập chia nước ra nhiều nhánh nên điểm thưởng gấp đôi.
     */
    public function calculateFlowScore(string $difficulty = 'easy'): int
    {
        return parent::calculateFlowScore($difficulty) * 2;
    }
}
