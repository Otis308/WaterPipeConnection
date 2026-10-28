<?php

namespace App\Domain\PipeGame\Pipes;

/**
 * Class StraightPipe (Ống Thẳng)
 * Kế thừa (extends) AbstractPipe và dùng parent::__construct() (Slide 8 & 10 Chương 2).
 */
class StraightPipe extends AbstractPipe
{
    public function __construct(int $row, int $col, int $rotation = 0, string $colorGroup = 'OngXanh')
    {
        parent::__construct($row, $col, $rotation, $colorGroup);
    }

    public function getPipeType(): string
    {
        return 'Thang';
    }

    public function getDefaultPorts(): array
    {
        // Mặc định ống thẳng mở cổng Trên (0) và Dưới (2) — khớp với CAU_HINH_ONG.Thang = [0, 2] trong BanDoGame.js
        return [0, 2];
    }

    public function getSvgAssetPath(): string
    {
        return "Images/svg/Ong/{$this->colorGroup}/{$this->colorGroup}-Thang.svg";
    }
}
