<?php

namespace App\Http\Requests;

use App\Services\FoodItemService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Item baru di daftar belanja. Batasannya sama dengan constraint shopping_list_items di database.
 * Keanggotaan rumah dijaga middleware "household" dan RLS insert shopping_list_items.
 */
class StoreShoppingItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'unit' => ['required', Rule::in(FoodItemService::UNITS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama barang.',
            'name.max' => 'Nama barang maksimal 100 karakter.',
            'quantity.*' => 'Jumlah harus angka lebih dari 0.',
            'unit.*' => 'Pilih satuan dari daftar.',
        ];
    }
}
