<?php

namespace App\Domain\PipeGame\Payments;

use App\Domain\PipeGame\Contracts\PaymentInterface;

/**
 * Class BankPayment
 * Implements PaymentInterface (Slide 13 Chương 2).
 */
class BankPayment implements PaymentInterface
{
    private string $bankName;
    private string $accountNumber;

    public function __construct(string $bankName = 'Vietcombank', string $accountNumber = '0071000888999')
    {
        $this->bankName = $bankName;
        $this->accountNumber = $accountNumber;
    }

    public function pay(float $amount): bool
    {
        return $amount >= 10000;
    }

    public function getGatewayName(): string
    {
        return "Chuyển khoản Ngân hàng ({$this->bankName})";
    }

    public function getTransactionReceipt(float $amount, string $itemName): array
    {
        return [
            'gateway' => $this->getGatewayName(),
            'account' => $this->accountNumber,
            'item' => $itemName,
            'amount_vnd' => number_format($amount, 0, ',', '.') . ' VNĐ',
            'status' => $this->pay($amount) ? 'SUCCESS' : 'FAILED',
            'tx_code' => 'BANK-' . strtoupper(substr(md5($this->bankName . $amount), 0, 8)),
        ];
    }
}
