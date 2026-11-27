<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsumptionRequest extends FormRequest
{
    /** Anggota rumah sudah dijamin middleware household; RLS menjaga kepemilikan baris. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'grams' => ['required', 'numeric', 'gt:0', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama makanan atau bahannya.',
            'grams.required' => 'Isi beratnya dalam gram.',
            'grams.gt' => 'Berat harus lebih dari 0 g.',
            'grams.max' => 'Berat maksimal 5.000 g per catatan.',
        ];
    }
}
