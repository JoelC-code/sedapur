<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Session Supabase palsu untuk tes: user sudah login (token masih berlaku satu jam)
 * dan, kecuali $household = null, sudah punya rumah.
 *
 * @param  array{id: int, name: string, invite_code: string, role: string}|null  $household
 */
function supabaseSession(?array $household = ['id' => 1, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K', 'role' => 'owner']): array
{
    return array_filter([
        'supabase' => [
            'access_token' => 'user-access-token',
            'refresh_token' => 'user-refresh-token',
            'expires_at' => time() + 3600,
            'user' => ['id' => '00000000-0000-4000-8000-00000000000a', 'email' => 'hans@contoh.com', 'name' => 'Hans Vere Liem'],
        ],
        'household' => $household,
    ]);
}
