<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
        'services.gemini.key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-3.5-flash-lite',
    ]);
    Http::preventStrayRequests();
});

function sampleRecipeRow(int $id = 1, bool $saved = false): array
{
    return [
        'id' => $id,
        'name' => 'Telur dadar bayam',
        'description' => 'Sarapan cepat sebelum bayam layu.',
        'steps' => ['Cuci bayam dan tiriskan.', 'Kocok telur lalu goreng bersama bayam.'],
        'servings' => 2,
        'cook_minutes' => 15,
        'calories_per_serving' => 320,
        'protein_per_serving' => 18,
        'carbs_per_serving' => 9,
        'fat_per_serving' => 22,
        'is_ai_generated' => true,
        'is_saved' => $saved,
        'created_at' => '2026-11-27T08:00:00+00:00',
        'recipe_ingredients' => [
            ['id' => 10, 'name' => 'Bayam', 'amount_text' => '1 ikat', 'quantity' => 1, 'unit' => 'ikat', 'grams' => 100, 'sort_order' => 0],
            ['id' => 11, 'name' => 'Daun bawang', 'amount_text' => '1 ikat', 'quantity' => 1, 'unit' => 'ikat', 'grams' => 20, 'sort_order' => 1],
        ],
    ];
}

function sampleFridgeRow(): array
{
    return [
        'id' => 5,
        'name' => 'Bayam',
        'category' => 'sayur',
        'quantity' => 2,
        'initial_quantity' => 2,
        'unit' => 'ikat',
        'weight_grams' => 300,
        'price' => 6000,
        'purchased_at' => now('Asia/Jakarta')->toDateString(),
        'expires_at' => now('Asia/Jakarta')->addDay()->toDateString(),
        'input_source' => 'manual',
        'status' => 'tersedia',
        'added_by' => '00000000-0000-4000-8000-00000000000a',
    ];
}

it('lists household recipes split into fridge recommendations and saved recipes', function () {
    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([sampleFridgeRow()]),
        'test.supabase.co/rest/v1/recipes*' => Http::response([
            sampleRecipeRow(1, false),
            [...sampleRecipeRow(2, true), 'name' => 'Sup ayam wortel'],
        ]),
    ]);

    $this->withSession(supabaseSession())
        ->get('/resep?q=bayam')
        ->assertOk()
        ->assertSee('Telur dadar bayam')
        ->assertSee('Sup ayam wortel')
        ->assertSee('Prioritas: bayam');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/rest/v1/recipes')
        && ($request->data()['household_id'] ?? null) === 'eq.1'
        && ($request->data()['name'] ?? null) === 'ilike.*bayam*');
});

it('shows recipe detail matched against available fridge items', function () {
    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([sampleFridgeRow()]),
        'test.supabase.co/rest/v1/recipes*' => Http::response([sampleRecipeRow(1, false)]),
    ]);

    $this->withSession(supabaseSession())
        ->get('/resep/1')
        ->assertOk()
        ->assertSee('Telur dadar bayam')
        ->assertSee('Besok')
        ->assertSee('Daun bawang · belum ada')
        ->assertSee('+1 ke belanja');
});

it('returns 404 when a recipe does not exist', function () {
    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([]),
        'test.supabase.co/rest/v1/recipes*' => Http::response([]),
    ]);

    $this->withSession(supabaseSession())
        ->get('/resep/999')
        ->assertNotFound();
});

it('generates recipes from fridge items via Gemini and saves them to Supabase', function () {
    $geminiPayload = [
        'candidates' => [[
            'content' => [
                'parts' => [[
                    'text' => json_encode([
                        'recipes' => [[
                            'name' => 'Tumis bayam bawang putih',
                            'description' => 'Cepat dimasak untuk menghabiskan bayam.',
                            'servings' => 2,
                            'cook_minutes' => 10,
                            'calories_per_serving' => 150,
                            'protein_per_serving' => 6,
                            'carbs_per_serving' => 12,
                            'fat_per_serving' => 8,
                            'ingredients' => [
                                ['name' => 'Bayam', 'amount_text' => '2 ikat', 'quantity' => 2, 'unit' => 'ikat', 'grams' => 300],
                                ['name' => 'Bawang putih', 'amount_text' => '3 siung', 'quantity' => 3, 'unit' => 'siung', 'grams' => 15],
                            ],
                            'steps' => ['Cuci bayam.', 'Tumis bawang putih.', 'Masukkan bayam hingga layu.'],
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ],
        ]],
    ];

    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([sampleFridgeRow()]),
        'generativelanguage.googleapis.com/*' => Http::response($geminiPayload),
        'test.supabase.co/rest/v1/recipes*' => Http::response([['id' => 42]]),
        'test.supabase.co/rest/v1/recipe_ingredients*' => Http::response([['id' => 100]]),
    ]);

    $this->withSession(supabaseSession())
        ->post('/resep/buat')
        ->assertRedirect(route('recipes.index'))
        ->assertSessionHas('status', '1 resep baru dibuat dari isi kulkasmu.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/recipes')
        && $request['name'] === 'Tumis bayam bawang putih'
        && $request['is_ai_generated'] === true);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/recipe_ingredients')
        && $request['name'] === 'Bawang putih'
        && $request['unit'] === 'buah');
});

it('refuses to generate recipes when the fridge is empty', function () {
    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([]),
    ]);

    $this->withSession(supabaseSession())
        ->post('/resep/buat')
        ->assertRedirect(route('recipes.index'))
        ->assertSessionHas('status', 'Tambahkan minimal 1 bahan ke kulkas dulu sebelum membuat resep dengan AI.');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

it('saves and deletes a recipe', function () {
    Http::fake([
        'test.supabase.co/rest/v1/recipes*' => Http::response([['id' => 1]]),
    ]);

    $this->withSession(supabaseSession())
        ->patch('/resep/1/simpan', ['is_saved' => true])
        ->assertRedirect(route('recipes.show', 1))
        ->assertSessionHas('status', 'Resep disimpan ke daftar tersimpan.');

    $this->withSession(supabaseSession())
        ->delete('/resep/1')
        ->assertRedirect(route('recipes.index'))
        ->assertSessionHas('status', 'Resep dihapus.');
});

it('adds missing recipe ingredients to the shopping list without duplicating unchecked items', function () {
    $recipe = sampleRecipeRow(1, false);
    $recipe['recipe_ingredients'][] = [
        'id' => 12,
        'name' => 'Kecap manis',
        'amount_text' => '1 botol',
        'quantity' => 1,
        'unit' => 'botol',
        'grams' => 135,
        'sort_order' => 2,
    ];

    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([sampleFridgeRow()]),
        'test.supabase.co/rest/v1/recipes*' => Http::response([$recipe]),
        'test.supabase.co/rest/v1/shopping_list_items*' => Http::sequence()
            ->push([['id' => 7, 'name' => 'Daun bawang', 'quantity' => 1, 'unit' => 'ikat', 'is_checked' => false, 'created_at' => '2026-11-27T08:00:00Z']])
            ->push([['id' => 8]]),
    ]);

    $this->withSession(supabaseSession())
        ->post('/resep/1/belanja')
        ->assertRedirect(route('recipes.show', 1))
        ->assertSessionHas('status', '1 bahan ditambahkan ke daftar belanja.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/shopping_list_items')
        && $request['name'] === 'Kecap manis'
        && $request['unit'] === 'botol');
});

it('logs eaten recipe portions into consumption_logs', function () {
    Http::fake([
        'test.supabase.co/rest/v1/food_items*' => Http::response([sampleFridgeRow()]),
        'test.supabase.co/rest/v1/recipes*' => Http::response([sampleRecipeRow(1, false)]),
        'test.supabase.co/rest/v1/consumption_logs*' => Http::response([['id' => 99]]),
    ]);

    $this->withSession(supabaseSession())
        ->post('/resep/1/konsumsi', ['portions' => '1.5'])
        ->assertRedirect(route('recipes.show', 1))
        ->assertSessionHas('status', 'Porsi dimakan dicatat ke Gizi & kalori.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/consumption_logs')
        && $request['name'] === 'Telur dadar bayam (1½ porsi)'
        && (float) $request['calories'] === 480.0
        && (float) $request['protein_g'] === 27.0
        && $request['source'] === 'estimasi_ai');
});
