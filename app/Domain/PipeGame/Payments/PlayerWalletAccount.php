<?php

namespace App\Domain\PipeGame\Payments;

/**
 * Class PlayerWalletAccount
 * Minh họa bài tập thực hành Encapsulation (Slide 6 & 27 Chương 2):
 * private $balance — ẩn số dư, chỉ thao tác qua deposit(), withdraw(), getBalance().
 */
class PlayerWalletAccount
{
    private string $ownerUsername;
    private float $balance;

    public function __construct(string $ownerUsername, float $initialBalance = 0.0)
    {
        $this->ownerUsername = $ownerUsername;
        $this->balance = max(0.0, $initialBalance);
    }

    public function deposit(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }
        $this->balance += $amount;
        return true;
    }

    public function withdraw(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->balance) {
            return false;
        }
        $this->balance -= $amount;
        return true;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getOwnerUsername(): string
    {
        return $this->ownerUsername;
    }
}
