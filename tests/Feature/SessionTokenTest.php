<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
});

it('gives a signed-in user a fresh access token without the refresh token', function () {
    $session = supabaseSession();

    $response = $this->withSession($session)->getJson(route('session.token'))
        ->assertOk()
        ->assertExactJson(['access_token' => 'user-access-token', 'expires_at' => $session['supabase']['expires_at']])
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->getContent())->not->toContain('user-refresh-token');
});

it('refreshes an almost expired token before handing it out', function () {
    Http::fake(['test.supabase.co/auth/v1/token*' => Http::response([
        'access_token' => 'new-access-token', 'refresh_token' => 'new-refresh-token', 'expires_at' => time() + 3600,
        'user' => ['id' => '00000000-0000-4000-8000-00000000000a', 'email' => 'hans@contoh.com', 'user_metadata' => ['full_name' => 'Hans Vere Liem']],
    ])]);
    $session = supabaseSession();
    $session['supabase']['expires_at'] = time() + 30;

    $this->withSession($session)->getJson(route('session.token'))
        ->assertOk()
        ->assertJsonPath('access_token', 'new-access-token')
        ->assertJsonMissingPath('refresh_token');
});

it('sends guests to the sign-in page', function () {
    $this->get(route('session.token'))->assertRedirect(route('login'));
});

it('answers background requests from guests with 401 and does not remember them for after login', function () {
    $this->getJson(route('session.token'))->assertUnauthorized();

    expect(session('url.intended'))->toBeNull();
});
