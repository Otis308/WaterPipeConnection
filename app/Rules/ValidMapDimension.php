<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom Rule kiểm tra kích thước lưới bản đồ hợp lệ của Game Nối Ống Nước (3x8 đến 5x12)
 */
class ValidMapDimension implements ValidationRule
{
    public const ALLOWED_SIZES = ['3x8', '3x10', '3x12', '5x4', '5x6', '5x8', '5x10', '5x12'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!in_array((string)$value, self::ALLOWED_SIZES, true)) {
            $fail("Kích thước bản đồ (:attribute) phải thuộc một trong các chuẩn: " . implode(', ', self::ALLOWED_SIZES));
        }
    }
}
