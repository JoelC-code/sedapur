<?php

use App\Services\NutritionLookupService;
use App\Services\TkpiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.supabase.url' => 'https://test.supabase.co']);
    Http::preventStrayRequests();
    Carbon::setTestNow('2026-11-27 05:00:00'); // 12:00 WIB, Jumat
});

afterEach(fn () => Carbon::setTestNow());

/** @param  array<string, mixed>  $overrides */
function per100g(array $overrides = []): array
{
    return ['name' => 'nasi putih', 'calories' => 130.0, 'protein' => 2.0, 'carbohydrates' => 28.0, 'fat' => 0.4,
        'metric_serving_amount' => 100, 'metric_serving_unit' => 'g', 'data_source' => 'tkpi', ...$overrides];
}

it('shows today\'s own consumption from the Data API', function () {
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([]),
        'test.supabase.co/rest/v1/consumption_daily*' => Http::response([
            ['day' => '2026-11-27', 'calories' => 615.5, 'protein_g' => 48, 'carbs_g' => 27, 'fat_g' => 18],
            ['day' => '2026-11-26', 'calories' => 1800, 'protein_g' => 60, 'carbs_g' => 200, 'fat_g' => 50],
        ]),
        'test.supabase.co/rest/v1/consumption_logs*' => Http::response([
            ['id' => 4, 'name' => 'Sup ayam', 'grams' => 350, 'calories' => 615.5, 'protein_g' => 48, 'carbs_g' => 27, 'fat_g' => 18, 'source' => 'tkpi', 'consumed_at' => '2026-11-27T05:10:00+00:00'],
        ]),
    ]);

    $this->withSession(supabaseSession())->get('/gizi')
        ->assertOk()
        ->assertSeeInOrder(['616', '/ 2.000 kkal', 'Protein 48 g'])
        ->assertSee('rata-rata 1.208 kkal')
        ->assertSeeInOrder(['Sup ayam', 'TKPI', '350 g · 12:10']);

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'consumption_daily')
        && str_contains(urldecode($request->url()), 'user_id=eq.00000000-0000-4000-8000-00000000000a')
        && str_contains(urldecode($request->url()), 'day=gte.2026-11-21'));
});

it('logs a portion with nutrition scaled from the per 100 g data', function () {
    $this->mock(NutritionLookupService::class)->shouldReceive('search')->with('Nasi putih')->andReturn(['status' => 'success', 'data' => per100g()]);
    Http::fake(['test.supabase.co/rest/v1/consumption_logs*' => Http::response([['id' => 1]], 201)]);

    $this->withSession(supabaseSession())->post('/gizi/konsumsi', ['name' => ' Nasi putih ', 'grams' => 150])
        ->assertRedirect(route('nutrition.index'))
        ->assertSessionHas('status', 'Nasi putih sudah dicatat.');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === [
        'household_id' => 1, 'name' => 'Nasi putih', 'grams' => 150.0,
        'calories' => 195.0, 'protein_g' => 3.0, 'carbs_g' => 42.0, 'fat_g' => 0.6, 'source' => 'tkpi',
    ]);
});

it('falls back to TKPI when FatSecret only knows a non gram serving', function () {
    $this->mock(NutritionLookupService::class)->shouldReceive('search')->andReturn(['status' => 'success', 'data' => per100g(['data_source' => 'fatsecret', 'metric_serving_unit' => '1 cup', 'calories' => 999.0])]);
    $this->mock(TkpiService::class)->shouldReceive('searchNutrition')->andReturn(per100g());
    Http::fake(['test.supabase.co/rest/v1/consumption_logs*' => Http::response([['id' => 1]], 201)]);

    $this->withSession(supabaseSession())->post('/gizi/konsumsi', ['name' => 'Nasi', 'grams' => 100])->assertRedirect();

    Http::assertSent(fn (Request $request) => $request['calories'] === 130.0 && $request['source'] === 'tkpi');
});

it('rejects unknown food and bad input without saving', function (array $input, string $field) {
    $this->mock(NutritionLookupService::class)->shouldReceive('search')->andReturn(null);
    $this->mock(TkpiService::class)->shouldReceive('searchNutrition')->andReturn(null);
    Http::fake();

    $this->withSession(supabaseSession())->from('/gizi')->post('/gizi/konsumsi', $input)
        ->assertRedirect('/gizi')
        ->assertSessionHasErrors($field);

    Http::assertNothingSent();
})->with([
    'tidak ada data gizi' => [['name' => 'Zzzz', 'grams' => 100], 'name'],
    'berat nol' => [['name' => 'Nasi', 'grams' => 0], 'grams'],
]);
