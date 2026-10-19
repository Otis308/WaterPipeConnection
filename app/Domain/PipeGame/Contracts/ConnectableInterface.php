<?php

namespace App\Domain\PipeGame\Contracts;

/**
 * Interface ConnectableInterface
 * Minh họa bài học Chương 2 (Slide 13-14): Interface định nghĩa hợp đồng hành vi (contract)
 * cho mọi loại ống nước trong game, không chứa thuộc tính hay thân hàm.
 */
interface ConnectableInterface
{
    /**
     * Lấy danh sách các hướng mở thực tế sau khi đã áp dụng góc xoay (0: Bắc/Trên, 1: Đông/Phải, 2: Nam/Dưới, 3: Tây/Trái)
     * @return int[]
     */
    public function getOpenDirections(): array;

    /**
     * Xoay ống nước một góc bội số của 90 độ
     */
    public function rotate(int $degrees = 90): void;

    /**
     * Kiểm tra xem ống hiện tại có thể nối thông dòng nước với ống hàng xóm ở hướng $direction hay không
     */
    public function canConnectWith(ConnectableInterface $neighbor, int $direction): bool;
}
