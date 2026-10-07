<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            //
            'delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
