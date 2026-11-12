<?php

namespace App\Http\Requests;

use App\Support\SupabaseSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JoinHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SupabaseSession::check();
    }

    /**
     * Form mengirim 6 kotak (kode[]); digabung jadi satu kode huruf besar.
     */
    protected function prepareForValidation(): void
    {
        $parts = $this->input('kode');
        $code = is_array($parts) ? implode('', array_map(fn ($part) => is_string($part) ? $part : '', $parts)) : (string) $this->input('invite_code');

        $this->merge(['invite_code' => strtoupper(preg_replace('/\s+/', '', $code))]);
    }

    /**
     * Abjad sama dengan private.generate_invite_code(): tanpa I, L, O, 0 dan 1.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invite_code' => ['required', 'string', 'regex:/^[A-HJKMNP-Z2-9]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invite_code.required' => 'Masukkan kode undangan.',
            'invite_code.regex' => 'Kode undangan terdiri dari 6 huruf/angka, tanpa O, I, L, 0 dan 1.',
        ];
    }
}
