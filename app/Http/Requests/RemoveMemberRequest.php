<?php

namespace App\Http\Requests;

use App\Support\SupabaseSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya owner yang boleh mengeluarkan anggota, dan tidak bisa mengeluarkan dirinya sendiri.
 * RLS household_members di Supabase menjaga hal yang sama sebagai lapis kedua.
 */
class RemoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (SupabaseSession::household()['role'] ?? null) === 'owner'
            && $this->route('userId') !== (SupabaseSession::user()['id'] ?? null);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
