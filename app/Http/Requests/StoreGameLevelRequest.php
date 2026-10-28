<?php

namespace App\Http\Requests;

use App\Rules\NoForbiddenWords;
use App\Rules\ValidMapDimension;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreGameLevelRequest
 * Minh họa Bài 9 (Slide 5-7 & 11):
 * - Tách toàn bộ logic kiểm tra dữ liệu ra khỏi Controller
 * - Tuỳ biến thông báo lỗi tiếng Việt qua messages() và attributes()
 * - Ràng buộc định dạng và kích thước ảnh upload (mimes:jpg,jpeg,png, max:2048 KB)
 */
class StoreGameLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role ?? '', ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'min:5', 'max:150', 'unique:game_levels,title', new NoForbiddenWords()],
            'category_id' => ['required', 'integer', 'exists:pipe_categories,id'],
            'map_size' => ['required', 'string', new ValidMapDimension()],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'time_limit_seconds' => ['required', 'integer', 'min:20', 'max:180'],
            'reward_stars' => ['required', 'integer', 'min:50', 'max:1000'],
            'description' => ['required', 'string', 'min:10', new NoForbiddenWords()],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tag_ids' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề màn chơi không được để trống.',
            'title.min' => 'Tiêu đề màn chơi phải có ít nhất :min ký tự.',
            'title.unique' => 'Tiêu đề màn chơi đã tồn tại, vui lòng chọn tên khác.',
            'category_id.required' => 'Vui lòng chọn danh mục chủ đề ống nước.',
            'description.required' => 'Mô tả màn chơi không được để trống.',
            'description.min' => 'Mô tả màn chơi phải có tối thiểu :min ký tự.',
            'thumbnail.image' => 'Tệp tải lên bắt buộc phải là hình ảnh.',
            'thumbnail.mimes' => 'Ảnh minh họa chỉ chấp nhận định dạng JPG, JPEG hoặc PNG.',
            'thumbnail.max' => 'Dung lượng ảnh không được vượt quá 2MB (2048 KB).',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Tiêu đề màn chơi',
            'category_id' => 'Danh mục chủ đề',
            'map_size' => 'Kích thước bản đồ',
            'difficulty' => 'Độ khó',
            'time_limit_seconds' => 'Thời gian giới hạn',
            'reward_stars' => 'Số sao thưởng',
            'description' => 'Mô tả chi tiết',
            'thumbnail' => 'Ảnh thu nhỏ (Thumbnail)',
        ];
    }
}
