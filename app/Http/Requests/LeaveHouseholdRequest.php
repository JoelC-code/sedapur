<?php

namespace App\Http\Requests;

use App\Support\SupabaseSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Member boleh keluar dari rumah. Owner tidak, agar rumah tidak tertinggal tanpa owner.
 */
class LeaveHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (SupabaseSession::household()['role'] ?? null) === 'member';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
