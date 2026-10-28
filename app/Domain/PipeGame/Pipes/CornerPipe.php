<?php

namespace App\Domain\PipeGame\Pipes;

/**
 * Class CornerPipe (Ống Chữ L)
 * Kế thừa AbstractPipe, bổ sung phương thức riêng swapPositionWith() cho ống vàng và override calculateFlowScore() (Đa hình).
 */
class CornerPipe extends AbstractPipe
{
    public function __construct(int $row, int $col, int $rotation = 0, string $colorGroup = 'OngXanh')
    {
        parent::__construct($row, $col, $rotation, $colorGroup);
    }

    public function getPipeType(): string
    {
        return 'ChuL';
    }

    public function getDefaultPorts(): array
    {
        // Mặc định ống chữ L mở cổng Phải (1) và Dưới (2) — khớp với CAU_HINH_ONG.ChuL = [1, 2]
        return [1, 2];
    }

    public function getSvgAssetPath(): string
    {
        return "Images/svg/Ong/{$this->colorGroup}/{$this->colorGroup}-ChuL.svg";
    }

    /**
     * Đa hình (Polymorphism - Slide 9 Chương 2):
     * Ống chữ L có độ khó định hướng cao hơn, đặc biệt nếu là Ống Vàng hoán đổi sẽ được thưởng thêm điểm.
     */
    public function calculateFlowScore(string $difficulty = 'easy'): int
    {
        $baseScore = parent::calculateFlowScore($difficulty);
        return $this->isSwappable() ? $baseScore + 15 : $baseScore + 5;
    }

    /**
     * Phương thức riêng của lớp con: Kiểm tra và hoán đổi vị trí giữa 2 ống vàng liền kề
     */
    public function swapPositionWith(CornerPipe $other): bool
    {
        if (!$this->isSwappable() || !$other->isSwappable()) {
            return false;
        }
        $manhattanDist = abs($this->getRow() - $other->getRow()) + abs($this->getCol() - $other->getCol());
        if ($manhattanDist !== 1) {
            return false;
        }
        $tempRow = $this->getRow();
        $tempCol = $this->getCol();
        $this->setRow($other->getRow());
        $this->setCol($other->getCol());
        $other->setRow($tempRow);
        $other->setCol($tempCol);
        return true;
    }
}
