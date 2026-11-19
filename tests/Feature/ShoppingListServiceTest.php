<?php

use App\Services\ShoppingListService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
});

it('lists the household shopping list as the user, unchecked first', function () {
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([
        ['id' => 4, 'name' => 'Bawang merah', 'quantity' => 1.5, 'unit' => 'kg', 'is_checked' => false, 'created_at' => '2026-11-19T08:00:00+00:00'],
    ])]);

    $items = app(ShoppingListService::class)->forHousehold('user-access-token', 1);

    expect($items)->toBe([['id' => 4, 'nama' => 'Bawang merah', 'jumlah' => '1,5', 'satuan' => 'kg', 'dicentang' => false]]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_contains($request->url(), '/rest/v1/shopping_list_items')
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request->hasHeader('apikey', 'sb_publishable_test')
        && $request['household_id'] === 'eq.1'
        && $request['order'] === 'is_checked.asc,created_at.desc');
});

it('adds an item without sending added_by', function () {
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([['id' => 7]], 201)]);

    $id = app(ShoppingListService::class)->add('user-access-token', 1, 'Telur', 10, 'butir');

    expect($id)->toBe(7);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/shopping_list_items?select=id')
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request->data() === ['household_id' => 1, 'name' => 'Telur', 'quantity' => 10.0, 'unit' => 'butir']);
});

it('checks an item for one household and records who checked it', function () {
    Carbon::setTestNow('2026-11-19 15:30:00');
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([['id' => 4]])]);

    $updated = app(ShoppingListService::class)->setChecked('user-access-token', 1, 4, true, 'user-uuid');

    expect($updated)->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains(urldecode($request->url()), 'id=eq.4&household_id=eq.1')
        && $request->data() === ['is_checked' => true, 'checked_by' => 'user-uuid', 'checked_at' => now()->toIso8601String()]);
});

it('clears who checked an item when the check is undone', function () {
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([['id' => 4]])]);

    app(ShoppingListService::class)->setChecked('user-access-token', 1, 4, false, 'user-uuid');

    Http::assertSent(fn (Request $request) => $request->data() === ['is_checked' => false, 'checked_by' => null, 'checked_at' => null]);
});

it('reports false when RLS lets no row change', function () {
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([])]);

    expect(app(ShoppingListService::class)->setChecked('user-access-token', 1, 99, true, 'user-uuid'))->toBeFalse()
        ->and(app(ShoppingListService::class)->remove('user-access-token', 1, 99))->toBeFalse();
});

it('removes an item filtered by id and household', function () {
    Http::fake(['test.supabase.co/rest/v1/shopping_list_items*' => Http::response([['id' => 4]])]);

    expect(app(ShoppingListService::class)->remove('user-access-token', 1, 4))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_contains(urldecode($request->url()), '/rest/v1/shopping_list_items?id=eq.4&household_id=eq.1')
        && $request->hasHeader('Authorization', 'Bearer user-access-token'));
});
