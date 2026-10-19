<?php

namespace App\Http\Requests;

use App\Rules\NoForbiddenWords;
use App\Rules\ValidMapDimension;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role ?? '', ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'min:5', 'max:150', new NoForbiddenWords()],
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
}
