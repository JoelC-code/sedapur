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
        ['role' => 'member', 'households' => ['id' => 7, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K']],
    ])]);

    $this->withSession(supabaseSession(household: null))->get('/')
        ->assertOk()
        ->assertSee('Kos Melati')
        ->assertSessionHas('household', ['id' => 7, 'name' => 'Kos Melati', 'invite_code' => 'MLT72K', 'role' => 'member']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request->hasHeader('apikey', 'sb_publishable_test')
        && str_contains(urldecode($request->url()), 'user_id=eq.00000000-0000-4000-8000-00000000000a'));
});

it('creates a household and makes the creator its owner', function () {
    Http::fake(['test.supabase.co/rest/v1/households' => Http::response([['id' => 9, 'name' => 'Kos Mawar', 'invite_code' => 'AB23CD']], 201)]);

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
            ['role' => 'member', 'households' => ['id' => 9, 'name' => 'Kos Mawar', 'invite_code' => 'AB23CD']],
        ]),
    ]);

    $this->withSession(supabaseSession(household: null))->post('/rumah/gabung', ['kode' => ['a', 'b', '2', '3', 'c', 'd']])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('household.id', 9);

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
