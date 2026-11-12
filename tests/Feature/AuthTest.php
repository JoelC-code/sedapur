<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
});

function supabaseAuthSession(string $accessToken = 'new-access-token'): array
{
    return [
        'access_token' => $accessToken,
        'refresh_token' => 'new-refresh-token',
        'expires_in' => 3600,
        'expires_at' => time() + 3600,
        'user' => ['id' => '00000000-0000-4000-8000-00000000000a', 'email' => 'hans@contoh.com', 'user_metadata' => ['full_name' => 'Hans Vere Liem']],
    ];
}

it('logs in with Supabase and stores the session on the server', function () {
    Http::fake(['test.supabase.co/auth/v1/token*' => Http::response(supabaseAuthSession())]);

    $this->post('/masuk', ['email' => 'hans@contoh.com', 'password' => 'rahasia123'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('supabase.access_token', 'new-access-token')
        ->assertSessionHas('supabase.user.name', 'Hans Vere Liem');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://test.supabase.co/auth/v1/token?grant_type=password'
        && $request->hasHeader('apikey', 'sb_publishable_test')
        && $request['email'] === 'hans@contoh.com');
});

it('shows an Indonesian message for a rejected login', function (string $errorCode, string $message) {
    Http::fake(['test.supabase.co/auth/v1/token*' => Http::response(['code' => 400, 'error_code' => $errorCode, 'msg' => 'x'], 400)]);

    $this->from('/masuk')->post('/masuk', ['email' => 'hans@contoh.com', 'password' => 'salah123'])
        ->assertRedirect('/masuk')
        ->assertSessionHasErrors(['email' => $message])
        ->assertSessionMissing('supabase');
})->with([
    ['invalid_credentials', 'Email atau kata sandi salah.'],
    ['email_not_confirmed', 'Email belum dikonfirmasi. Buka link di email kamu dulu.'],
]);

it('asks the user to confirm their email when Supabase returns no session', function () {
    Http::fake(['test.supabase.co/auth/v1/signup' => Http::response(['id' => 'u1', 'email' => 'baru@contoh.com'])]);

    $this->post('/daftar', ['name' => 'Budi', 'email' => 'baru@contoh.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status')
        ->assertSessionMissing('supabase');

    Http::assertSent(fn (Request $request) => $request['data'] === ['full_name' => 'Budi']);
});

it('logs a new user in right away when email confirmation is off', function () {
    Http::fake(['test.supabase.co/auth/v1/signup' => Http::response(supabaseAuthSession())]);

    $this->post('/daftar', ['name' => 'Hans Vere Liem', 'email' => 'hans@contoh.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect(route('household.setup'))
        ->assertSessionHas('supabase.access_token');
});

it('validates the registration form before calling Supabase', function (array $input, string $field) {
    Http::fake();

    $this->post('/daftar', [...['name' => 'Budi', 'email' => 'budi@contoh.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], ...$input])
        ->assertSessionHasErrors($field);

    Http::assertNothingSent();
})->with([
    'kata sandi pendek' => [['password' => 'pendek', 'password_confirmation' => 'pendek'], 'password'],
    'ulangi beda' => [['password_confirmation' => 'beda12345'], 'password'],
    'email salah' => [['email' => 'bukan-email'], 'email'],
    'nama kosong' => [['name' => ''], 'name'],
]);

it('sends guests to the login page', function () {
    $this->get('/kulkas')->assertRedirect(route('login'));
    $this->get('/rumah/mulai')->assertRedirect(route('login'));
});

it('keeps logged in users away from the login page', function () {
    $this->withSession(supabaseSession())->get('/masuk')->assertRedirect(route('dashboard'));
});

it('refreshes an access token that is about to expire', function () {
    Http::fake(['test.supabase.co/auth/v1/token*' => Http::response(supabaseAuthSession('refreshed-token'))]);
    $session = supabaseSession();
    $session['supabase']['expires_at'] = time() + 10;

    $this->withSession($session)->get('/')
        ->assertOk()
        ->assertSessionHas('supabase.access_token', 'refreshed-token');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'grant_type=refresh_token')
        && $request['refresh_token'] === 'user-refresh-token');
});

it('logs out when the refresh token is no longer valid', function () {
    Http::fake(['test.supabase.co/auth/v1/token*' => Http::response(['error_code' => 'refresh_token_not_found'], 400)]);
    $session = supabaseSession();
    $session['supabase']['expires_at'] = time() - 5;

    $this->withSession($session)->get('/')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('supabase');
});

it('signs out of Supabase and clears the session', function () {
    Http::fake(['test.supabase.co/auth/v1/logout' => Http::response(null, 204)]);

    $this->withSession(supabaseSession())->post('/keluar')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('supabase')
        ->assertSessionMissing('household');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://test.supabase.co/auth/v1/logout'
        && $request->hasHeader('Authorization', 'Bearer user-access-token'));
});
