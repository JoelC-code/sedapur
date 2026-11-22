<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.supabase.url' => 'https://test.supabase.co']);
    Http::preventStrayRequests();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('shows this month waste from the database views with the change from last month', function () {
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([]),
        'test.supabase.co/rest/v1/food_waste_monthly*' => Http::response([
            ['month' => '2026-11-01', 'cooked_count' => 6, 'wasted_count' => 2, 'wasted_value' => 15000],
            ['month' => '2026-10-01', 'cooked_count' => 3, 'wasted_count' => 4, 'wasted_value' => 30000],
        ]),
        'test.supabase.co/rest/v1/food_waste_items*' => Http::response([
            ['name' => 'Susu UHT', 'wasted_count' => 1, 'wasted_value' => 9250],
        ]),
    ]);

    $this->withSession(supabaseSession())->get('/food-waste')
        ->assertOk()
        ->assertSeeInOrder(['Rp 15.000', '-50%', 'dibanding Oktober', 'Rp 30.000'])
        ->assertSee('75%')
        ->assertSeeInOrder(['Susu UHT', '1×', 'Rp 9.250']);

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'food_waste_monthly')
        && str_contains(urldecode($request->url()), 'household_id=eq.1')
        && str_contains(urldecode($request->url()), 'month=gte.2026-06-01'));
    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'food_waste_items')
        && str_contains(urldecode($request->url()), 'month=eq.2026-11-01'));
});

it('shows zeros instead of failing when there is no data or Supabase is down', function () {
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([]),
        'test.supabase.co/rest/v1/food_waste*' => Http::response(['message' => 'boom'], 500),
    ]);

    $this->withSession(supabaseSession())->get('/food-waste')
        ->assertOk()
        ->assertSee('Rp 0')
        ->assertSee('Belum ada bahan yang dibuang bulan ini.');
});
