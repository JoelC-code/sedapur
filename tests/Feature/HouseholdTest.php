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

it('sends a user without a household to the setup page', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([])]);

    $this->withSession(supabaseSession(household: null))->get('/')
        ->assertRedirect(route('household.setup'));
});

it('loads the household of a returning member through the Data API with their token', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([
        ['role' => 'member', 'households' => ['id' => 7, 'name' => 'Kos Melati']],
    ])]);

    $this->withSession(supabaseSession(household: null))->get('/')
        ->assertOk()
        ->assertSee('Kos Melati')
        ->assertSessionHas('household', ['id' => 7, 'name' => 'Kos Melati', 'invite_code' => null, 'role' => 'member']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'household_invite_code'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request->hasHeader('apikey', 'sb_publishable_test')
        && str_contains(urldecode($request->url()), 'user_id=eq.00000000-0000-4000-8000-00000000000a'));
});

it('creates a household and makes the creator its owner', function () {
    Http::fake([
        'test.supabase.co/rest/v1/households*' => Http::response([['id' => 9, 'name' => 'Kos Mawar']], 201),
        'test.supabase.co/rest/v1/rpc/household_invite_code' => Http::response(json_encode('AB23CD')),
    ]);

    $this->withSession(supabaseSession(household: null))->post('/rumah', ['name' => '  Kos Mawar  '])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('household', ['id' => 9, 'name' => 'Kos Mawar', 'invite_code' => 'AB23CD', 'role' => 'owner']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data() === ['name' => 'Kos Mawar']
        && $request->hasHeader('Authorization', 'Bearer user-access-token'));
});

it('joins a household with an invite code typed into six boxes', function () {
    Http::fake([
        'test.supabase.co/rest/v1/rpc/join_household' => Http::response(9),
        'test.supabase.co/rest/v1/household_members*' => Http::response([
            ['role' => 'member', 'households' => ['id' => 9, 'name' => 'Kos Mawar']],
        ]),
    ]);

    $this->withSession(supabaseSession(household: null))->post('/rumah/gabung', ['kode' => ['a', 'b', '2', '3', 'c', 'd']])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('household', ['id' => 9, 'name' => 'Kos Mawar', 'invite_code' => null, 'role' => 'member']);

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'household_id=eq.9'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/rpc/join_household')
        && $request->data() === ['p_invite_code' => 'AB23CD']);
});

it('explains an unknown invite code in Indonesian', function () {
    Http::fake(['test.supabase.co/rest/v1/rpc/join_household' => Http::response(['code' => 'P0002', 'message' => 'Kode undangan tidak ditemukan'], 404)]);

    $this->withSession(supabaseSession(household: null))->from('/rumah/mulai')
        ->post('/rumah/gabung', ['kode' => str_split('ZZZZZZ')])
        ->assertRedirect('/rumah/mulai')
        ->assertSessionHasErrors(['invite_code' => 'Kode undangan tidak ditemukan. Cek lagi ke pemilik rumah.'])
        ->assertSessionMissing('household');
});

it('rejects a malformed invite code without calling Supabase', function (array $code) {
    Http::fake();

    $this->withSession(supabaseSession(household: null))->post('/rumah/gabung', ['kode' => $code])
        ->assertSessionHasErrors('invite_code');

    Http::assertNothingSent();
})->with([
    'terlalu pendek' => [['A', 'B', 'C']],
    'huruf O dan angka 0' => [str_split('ABCO0D')],
]);

it('does not let guests create or join a household', function () {
    Http::fake();

    $this->post('/rumah', ['name' => 'Kos Mawar'])->assertRedirect(route('login'));
    $this->post('/rumah/gabung', ['kode' => str_split('AB23CD')])->assertRedirect(route('login'));

    Http::assertNothingSent();
});

it('shows the real household members with their names', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([
        ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
        ['user_id' => '00000000-0000-4000-8000-00000000000b', 'role' => 'member', 'joined_at' => '2026-11-13T02:00:00+00:00', 'profiles' => ['full_name' => 'Rina']],
    ])]);

    $this->withSession(supabaseSession(['id' => 1, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K', 'role' => 'owner']))->get('/rumah')
        ->assertOk()
        ->assertSee('Anggota · 2')
        ->assertSee('Hans Vere Liem (kamu)')
        ->assertSee('Rina')
        ->assertSee('Keluarkan');

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'household_id=eq.1')
        && str_contains(urldecode($request->url()), 'profiles(full_name)'));
});

it('lets the owner remove another member through the Data API', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([['user_id' => '00000000-0000-4000-8000-00000000000b']])]);

    $this->withSession(supabaseSession())->from('/rumah')
        ->delete('/rumah/anggota/00000000-0000-4000-8000-00000000000b')
        ->assertRedirect('/rumah')
        ->assertSessionHas('status', 'Anggota sudah dikeluarkan dari rumah.');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_contains(urldecode($request->url()), 'user_id=eq.00000000-0000-4000-8000-00000000000b')
        && $request->hasHeader('Authorization', 'Bearer user-access-token'));
});

it('forbids members and self removal before calling Supabase', function (?array $household, string $userId) {
    Http::fake();

    $this->withSession(supabaseSession($household))->delete("/rumah/anggota/{$userId}")->assertForbidden();

    Http::assertNothingSent();
})->with([
    'member biasa' => [['id' => 1, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K', 'role' => 'member'], '00000000-0000-4000-8000-00000000000b'],
    'owner keluarkan diri sendiri' => [['id' => 1, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K', 'role' => 'owner'], '00000000-0000-4000-8000-00000000000a'],
]);

it('shows the invite code only to the owner', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([])]);

    $this->withSession(supabaseSession())->get('/rumah')
        ->assertSee('MLT72K')
        ->assertDontSee('Keluar dari rumah ini');

    $this->withSession(supabaseSession(['id' => 1, 'name' => 'Kos Melati', 'invite_code' => null, 'role' => 'member']))->get('/rumah')
        ->assertDontSee('Bagikan kode')
        ->assertSee('Kode undangan hanya bisa dilihat dan dibagikan oleh owner rumah.')
        ->assertSee('Keluar dari rumah ini');
});

it('lets a member leave the household', function () {
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([['user_id' => '00000000-0000-4000-8000-00000000000a']])]);

    $this->withSession(supabaseSession(['id' => 1, 'name' => 'Kos Melati', 'invite_code' => null, 'role' => 'member']))
        ->delete('/rumah/keluar')
        ->assertRedirect(route('household.setup'))
        ->assertSessionMissing('household')
        ->assertSessionHas('supabase');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_contains(urldecode($request->url()), 'user_id=eq.00000000-0000-4000-8000-00000000000a'));
});

it('does not let the owner leave their own household', function () {
    Http::fake();

    $this->withSession(supabaseSession())->delete('/rumah/keluar')->assertForbidden();

    Http::assertNothingSent();
});
