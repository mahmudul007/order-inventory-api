<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
        ];
    }
}
