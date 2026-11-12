<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Error dari Supabase (Auth atau Data API) dengan kode mesin yang bisa dicocokkan.
 *
 * Auth mengirim `error_code` (mis. invalid_credentials), Data API/PostgREST mengirim `code`
 * (mis. P0002 dari fungsi join_household). Pesan untuk user dibuat di controller, bukan di sini.
 */
class SupabaseException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $errorCode = $body['error_code'] ?? $body['code'] ?? 'unknown';

        return new self(
            errorCode: is_string($errorCode) ? $errorCode : (string) $response->status(),
            status: $response->status(),
            message: (string) ($body['msg'] ?? $body['message'] ?? $body['error_description'] ?? ''),
        );
    }
}
