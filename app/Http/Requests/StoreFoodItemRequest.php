<?php

namespace App\Http\Requests;

use App\Services\FoodItemService;
use App\Support\DemoData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bahan baru dari form input manual. Batasannya sama dengan constraint food_items di database,
 * supaya kesalahan input dijawab dengan pesan yang jelas, bukan error dari Supabase.
 * Keanggotaan rumah dijaga middleware "household" dan RLS insert food_items.
 */
class StoreFoodItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(DemoData::CATEGORIES))],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit' => ['required', Rule::in(FoodItemService::UNITS)],
            'weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'price' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date', Rule::when($this->filled('purchased_at'), 'after_or_equal:purchased_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama bahan.',
            'name.max' => 'Nama bahan maksimal 120 karakter.',
            'category.*' => 'Pilih kategori.',
            'quantity.*' => 'Jumlah harus angka lebih dari 0.',
            'unit.*' => 'Pilih satuan dari daftar.',
            'weight_grams.*' => 'Berat harus angka lebih dari 0.',
            'price.*' => 'Harga harus angka bulat, tanpa titik atau "Rp".',
            'purchased_at.*' => 'Tanggal beli tidak valid.',
            'expires_at.required' => 'Isi tanggal kedaluwarsa.',
            'expires_at.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal beli.',
            'expires_at.*' => 'Tanggal kedaluwarsa tidak valid.',
        ];
    }
}
