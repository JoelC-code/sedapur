<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Centang atau batalkan centang item belanja. Item yang boleh diubah dijaga RLS update shopping_list_items.
 */
class UpdateShoppingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_checked' => ['required', 'boolean'],
        ];
    }
}
