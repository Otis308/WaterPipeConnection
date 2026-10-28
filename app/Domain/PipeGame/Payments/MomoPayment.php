<?php

namespace App\Domain\PipeGame\Payments;

use App\Domain\PipeGame\Contracts\PaymentInterface;

/**
 * Class MomoPayment
 * Implements PaymentInterface (Slide 13 Chương 2).
 */
class MomoPayment implements PaymentInterface
{
    private string $phoneNumber;

    public function __construct(string $phoneNumber = '0901234567')
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function pay(float $amount): bool
    {
        return $amount > 0;
    }

    public function getGatewayName(): string
    {
        return 'Ví điện tử MoMo';
    }

    public function getTransactionReceipt(float $amount, string $itemName): array
    {
        return [
            'gateway' => $this->getGatewayName(),
            'account' => $this->phoneNumber,
            'item' => $itemName,
            'amount_vnd' => number_format($amount, 0, ',', '.') . ' VNĐ',
            'status' => $this->pay($amount) ? 'SUCCESS' : 'FAILED',
            'tx_code' => 'MOMO-' . strtoupper(substr(md5($itemName . $amount), 0, 8)),
        ];
    }
}
