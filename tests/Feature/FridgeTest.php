<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
});

function fridgeRow(array $overrides = []): array
{
    return [
        'id' => 3, 'name' => 'Susu UHT', 'category' => 'susu_telur', 'quantity' => 1000, 'unit' => 'ml',
        'weight_grams' => null, 'price' => 20000, 'purchased_at' => '2026-11-18', 'expires_at' => now('Asia/Jakarta')->addDays(2)->toDateString(),
        'input_source' => 'manual', 'status' => 'tersedia', 'added_by' => '00000000-0000-4000-8000-00000000000a',
        ...$overrides,
    ];
}

function fakeMembersAnd(array $foodItems): void
{
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([
            ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
        ]),
        'test.supabase.co/rest/v1/food_items*' => Http::sequence($foodItems),
    ]);
}

it('lists the household fridge from the Data API as the user', function () {
    fakeMembersAnd([Http::response([fridgeRow()])]);

    $this->withSession(supabaseSession())->get('/kulkas?kategori=susu_telur&q=susu')
        ->assertOk()
        ->assertSee('Susu UHT')
        ->assertSee('Hans Vere Liem')
        ->assertSee('1000 ml');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/rest/v1/food_items')
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request['household_id'] === 'eq.1'
        && $request['status'] === 'eq.tersedia'
        && $request['category'] === 'eq.susu_telur'
        && $request['name'] === 'ilike.*susu*');
});

it('subscribes the fridge list to Realtime with only the publishable key', function () {
    config(['services.supabase.secret_key' => 'sb_secret_test']);
    fakeMembersAnd([Http::response([fridgeRow()])]);

    $this->withSession(supabaseSession())->get('/kulkas')
        ->assertOk()
        ->assertSee('data-realtime-table="food_items"', false)
        ->assertSee('data-household-id="1"', false)
        ->assertSee('sb_publishable_test')
        ->assertDontSee('sb_secret_test')
        ->assertDontSee('user-access-token');
});

it('shows an item without an expiry date', function () {
    fakeMembersAnd([Http::response([]), Http::response([fridgeRow(['expires_at' => null])])]);

    $this->withSession(supabaseSession())->get('/kulkas/3')
        ->assertOk()
        ->assertSee('Tanggal kedaluwarsa belum diisi')
        ->assertSee('Tandai dimasak');
});

it('returns 404 for an item outside the household', function () {
    fakeMembersAnd([Http::response([fridgeRow()]), Http::response([])]);

    $this->withSession(supabaseSession())->get('/kulkas/99')->assertNotFound();
});

it('tells the user when the fridge cannot be loaded', function () {
    fakeMembersAnd([Http::response(['message' => 'boom'], 500)]);

    $this->withSession(supabaseSession())->get('/kulkas')
        ->assertOk()
        ->assertSee('Isi kulkas belum bisa dimuat');
});

it('adds a manual item to the household fridge', function () {
    fakeMembersAnd([Http::response([['id' => 12]], 201)]);

    $this->withSession(supabaseSession())->post('/kulkas', [
        'name' => 'Bawang merah', 'category' => 'bumbu', 'quantity' => '1', 'unit' => 'kg',
        'price' => '42000', 'purchased_at' => '2026-11-19', 'expires_at' => '2026-12-03',
    ])->assertRedirect(route('fridge.show', 12));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request['household_id'] === 1
        && $request['name'] === 'Bawang merah'
        && $request['unit'] === 'kg'
        && $request['input_source'] === 'manual'
        && ! isset($request['added_by']));
});

it('rejects a unit outside the list and an expiry before the purchase date', function () {
    $this->withSession(supabaseSession())->post('/kulkas', [
        'name' => 'Telur', 'category' => 'susu_telur', 'quantity' => '10', 'unit' => 'pcs',
        'purchased_at' => '2026-11-19', 'expires_at' => '2026-11-01',
    ])->assertSessionHasErrors(['unit', 'expires_at']);

    Http::assertNothingSent();
});

it('marks an item as wasted through the Data API', function () {
    fakeMembersAnd([Http::response([['id' => 3]])]);

    $this->withSession(supabaseSession())->patch('/kulkas/3/status', ['status' => 'dibuang'])
        ->assertRedirect(route('fridge.index'));

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains(urldecode($request->url()), 'id=eq.3&household_id=eq.1')
        && $request->data() === ['status' => 'dibuang']);
});

it('returns 404 when RLS lets no row change', function () {
    fakeMembersAnd([Http::response([])]);

    $this->withSession(supabaseSession())->patch('/kulkas/3/status', ['status' => 'dimasak'])->assertNotFound();
});

it('only accepts cooked or wasted as a new status', function () {
    $this->withSession(supabaseSession())->patch('/kulkas/3/status', ['status' => 'tersedia'])
        ->assertSessionHasErrors('status');
});
