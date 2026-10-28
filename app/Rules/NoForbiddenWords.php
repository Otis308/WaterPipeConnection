<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom Validation Rule: NoForbiddenWords
 * Áp dụng trực tiếp Bài 9 (Slide 10 - Lập trình mã nguồn mở HUIT):
 * Đóng gói logic kiểm tra từ cấm thành một class tái sử dụng được ở nhiều Form Request.
 */
class NoForbiddenWords implements ValidationRule
{
    private array $forbidden = ['test', 'spam', 'xxx', 'hack', 'cheat'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = mb_strtolower((string)$value, 'UTF-8');
        foreach ($this->forbidden as $word) {
            if (str_contains($text, $word)) {
                $fail("Trường :attribute chứa từ không được phép sử dụng: '{$word}'.");
                return;
            }
        }
    }

    public static function checkString(string $value): ?string
    {
        $text = mb_strtolower($value, 'UTF-8');
        foreach (['test', 'spam', 'xxx', 'hack', 'cheat'] as $word) {
            if (str_contains($text, $word)) {
                return "Chứa từ không được phép: '{$word}'";
            }
        }
        return null;
    }
}
