<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogRecipePortionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'portions' => ['required', 'numeric', 'in:0.5,1,1.5,2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'portions.required' => 'Pilih jumlah porsi yang dimakan.',
            'portions.numeric' => 'Jumlah porsi harus berupa angka.',
            'portions.in' => 'Pilih salah satu porsi yang tersedia (½, 1, 1½, atau 2).',
        ];
    }
}
