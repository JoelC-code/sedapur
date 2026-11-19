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

function fakeShoppingList(array $responses): void
{
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([
            ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
        ]),
        'test.supabase.co/rest/v1/shopping_list_items*' => Http::sequence($responses),
    ]);
}

it('sends guests to the sign-in page', function () {
    $this->get('/belanja')->assertRedirect(route('login'));
    $this->post('/belanja', ['name' => 'Telur', 'quantity' => '10', 'unit' => 'butir'])->assertRedirect(route('login'));

    Http::assertNothingSent();
});

it('lists the household shopping list with checked items last', function () {
    fakeShoppingList([Http::response([
        ['id' => 4, 'name' => 'Bawang merah', 'quantity' => 1.5, 'unit' => 'kg', 'is_checked' => false, 'created_at' => '2026-11-19T08:00:00+00:00'],
        ['id' => 5, 'name' => 'Telur', 'quantity' => 10, 'unit' => 'butir', 'is_checked' => true, 'created_at' => '2026-11-19T07:00:00+00:00'],
    ])]);

    $this->withSession(supabaseSession())->get('/belanja')
        ->assertOk()
        ->assertSeeInOrder(['Perlu dibeli', 'Bawang merah', '1,5 kg', 'Sudah dibeli', 'Telur', '10 butir']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/rest/v1/shopping_list_items')
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request['household_id'] === 'eq.1');
});

it('shows an empty state and a load error', function () {
    fakeShoppingList([Http::response([]), Http::response(['message' => 'boom'], 500)]);

    $this->withSession(supabaseSession())->get('/belanja')->assertOk()->assertSee('Daftar belanja masih kosong');
    $this->withSession(supabaseSession())->get('/belanja')->assertOk()->assertSee('Daftar belanja belum bisa dimuat');
});

it('adds an item to the household shopping list', function () {
    fakeShoppingList([Http::response([['id' => 7]], 201)]);

    $this->withSession(supabaseSession())->post('/belanja', ['name' => 'Telur', 'quantity' => '10', 'unit' => 'butir'])
        ->assertRedirect(route('shopping.index'))
        ->assertSessionHas('status', 'Telur masuk ke daftar belanja.');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request->data() === ['household_id' => 1, 'name' => 'Telur', 'quantity' => 10.0, 'unit' => 'butir']);
});

it('rejects an empty name, a zero quantity and a unit outside the list', function () {
    $this->withSession(supabaseSession())->post('/belanja', ['name' => '', 'quantity' => '0', 'unit' => 'pcs'])
        ->assertSessionHasErrors(['name', 'quantity', 'unit']);

    $this->withSession(supabaseSession())->patch('/belanja/4', ['is_checked' => 'ya'])
        ->assertSessionHasErrors('is_checked');

    Http::assertNothingSent();
});

it('checks an item as the signed-in user', function () {
    fakeShoppingList([Http::response([['id' => 4]])]);

    $this->withSession(supabaseSession())->patch('/belanja/4', ['is_checked' => '1'])
        ->assertRedirect(route('shopping.index'));

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains(urldecode($request->url()), 'id=eq.4&household_id=eq.1')
        && $request['is_checked'] === true
        && $request['checked_by'] === '00000000-0000-4000-8000-00000000000a');
});

it('removes an item from the household shopping list', function () {
    fakeShoppingList([Http::response([['id' => 4]])]);

    $this->withSession(supabaseSession())->delete('/belanja/4')
        ->assertRedirect(route('shopping.index'));

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_contains(urldecode($request->url()), 'id=eq.4&household_id=eq.1'));
});

it('returns 404 when RLS lets no row change', function () {
    fakeShoppingList([Http::response([]), Http::response([])]);

    $this->withSession(supabaseSession())->patch('/belanja/99', ['is_checked' => '1'])->assertNotFound();
    $this->withSession(supabaseSession())->delete('/belanja/99')->assertNotFound();
});
