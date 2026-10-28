<?php

namespace App\Domain\PipeGame\Pipes;

use App\Domain\PipeGame\Contracts\ConnectableInterface;
use App\Domain\PipeGame\Contracts\ScorableInterface;

/**
 * Abstract Class AbstractPipe
 * Minh họa toàn diện Chương 2 (Slide 4-12):
 * - Abstract Class làm khuôn mẫu cơ sở, không thể khởi tạo trực tiếp bằng `new AbstractPipe()`.
 * - Encapsulation (Tính đóng gói): Thuộc tính private/protected, kiểm soát truy cập qua Getter/Setter.
 * - Constructor (__construct) & Destructor (__destruct) quản lý vòng đời đối tượng.
 * - Từ khóa $this đại diện cho đối tượng hiện tại.
 */
abstract class AbstractPipe implements ConnectableInterface, ScorableInterface
{
    private int $row;
    private int $col;
    protected int $rotation;
    protected string $colorGroup; // OngXanh (xoay được), OngDo (cố định), OngVang (hoán đổi được)
    protected bool $waterFilled = false;
    private static int $activeInstances = 0;

    public function __construct(int $row, int $col, int $rotation = 0, string $colorGroup = 'OngXanh')
    {
        $this->setRow($row);
        $this->setCol($col);
        $this->setRotation($rotation);
        $this->setColorGroup($colorGroup);
        self::$activeInstances++;
    }

    public function __destruct()
    {
        // Giải phóng tài nguyên hoặc giảm bộ đếm vòng đời object (Slide 10 Chương 2)
        if (self::$activeInstances > 0) {
            self::$activeInstances--;
        }
    }

    /**
     * Abstract methods buộc các lớp con (StraightPipe, CornerPipe, CrossPipe) phải override (Slide 12)
     */
    abstract public function getPipeType(): string;

    /**
     * Trả về các hướng mở mặc định ở góc 0 độ (0: Trên, 1: Phải, 2: Dưới, 3: Trái)
     * @return int[]
     */
    abstract public function getDefaultPorts(): array;

    abstract public function getSvgAssetPath(): string;

    // ==================== GETTER & SETTER (ENCAPSULATION - SLIDE 6 & 7) ====================

    public function getRow(): int
    {
        return $this->row;
    }

    public function setRow(int $row): void
    {
        if ($row >= 1 && $row <= 10) {
            $this->row = $row;
        } else {
            $this->row = 1;
        }
    }

    public function getCol(): int
    {
        return $this->col;
    }

    public function setCol(int $col): void
    {
        if ($col >= 1 && $col <= 20) {
            $this->col = $col;
        } else {
            $this->col = 1;
        }
    }

    public function getRotation(): int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): void
    {
        // Chuẩn hóa góc xoay về 0, 90, 180, 270
        $normalized = (($rotation % 360) + 360) % 360;
        $this->rotation = (int)(round($normalized / 90) * 90) % 360;
    }

    public function getColorGroup(): string
    {
        return $this->colorGroup;
    }

    public function setColorGroup(string $colorGroup): void
    {
        $allowed = ['OngXanh', 'OngDo', 'OngVang', 'Special'];
        $this->colorGroup = in_array($colorGroup, $allowed, true) ? $colorGroup : 'OngXanh';
    }

    public function isWaterFilled(): bool
    {
        return $this->waterFilled;
    }

    public function setWaterFilled(bool $filled): void
    {
        $this->waterFilled = $filled;
    }

    public function isRotatable(): bool
    {
        return $this->colorGroup === 'OngXanh';
    }

    public function isSwappable(): bool
    {
        return $this->colorGroup === 'OngVang';
    }

    // ==================== TRIỂN KHAI CONNECTABLE INTERFACE ====================

    public function rotate(int $degrees = 90): void
    {
        // Ống đỏ (OngDo) và Ống vàng (OngVang) trong game không xoay trực tiếp khi click
        if ($this->isRotatable()) {
            $this->setRotation($this->rotation + $degrees);
        }
    }

    public function getOpenDirections(): array
    {
        $steps = (int)($this->rotation / 90) % 4;
        $ports = [];
        foreach ($this->getDefaultPorts() as $port) {
            $ports[] = ($port + $steps) % 4;
        }
        return $ports;
    }

    public function canConnectWith(ConnectableInterface $neighbor, int $direction): bool
    {
        // $direction: hướng từ ống hiện tại sang ống hàng xóm (0: Lên, 1: Phải, 2: Xuống, 3: Trái)
        if (!in_array($direction, $this->getOpenDirections(), true)) {
            return false;
        }
        $oppositeDirection = ($direction + 2) % 4;
        return in_array($oppositeDirection, $neighbor->getOpenDirections(), true);
    }

    // ==================== TRIỂN KHAI SCORABLE INTERFACE ====================

    public function calculateFlowScore(string $difficulty = 'easy'): int
    {
        $base = 10; // Mặc định mỗi ống nước chảy qua cộng 10 điểm (khớp logic BanDoGame.js)
        $multiplier = match ($difficulty) {
            'hard' => 3,
            'medium' => 2,
            default => 1,
        };
        return $base * $multiplier;
    }

    public function getPipeSummary(): array
    {
        return [
            'type' => $this->getPipeType(),
            'position' => "({$this->row}, {$this->col})",
            'color_group' => $this->colorGroup,
            'rotation' => $this->rotation,
            'open_directions' => $this->getOpenDirections(),
            'rotatable' => $this->isRotatable(),
            'swappable' => $this->isSwappable(),
            'svg_asset' => $this->getSvgAssetPath(),
            'score_easy' => $this->calculateFlowScore('easy'),
            'score_hard' => $this->calculateFlowScore('hard'),
        ];
    }

    public static function getActiveInstancesCount(): int
    {
        return self::$activeInstances;
    }
}
