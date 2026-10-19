<?php

namespace App\Domain\PipeGame\Contracts;

/**
 * Interface PaymentInterface
 * Áp dụng trực tiếp ví dụ chuẩn từ Slide 13 Chương 2 (Lập trình OOP PHP - HUIT):
 * MomoPayment và BankPayment đều implements PaymentInterface nhưng xử lý thanh toán theo cách riêng.
 */
interface PaymentInterface
{
    public function pay(float $amount): bool;

    public function getGatewayName(): string;

    public function getTransactionReceipt(float $amount, string $itemName): array;
}
