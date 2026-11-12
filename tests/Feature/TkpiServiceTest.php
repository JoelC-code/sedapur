<?php

use App\Services\TkpiService;
use Database\Seeders\TkpiItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function addTkpiItem(string $name, float $calories): void
{
    DB::table('tkpi_items')->insert([
        'nama_bahan' => $name,
        'kalori_100g' => $calories,
        'protein_100g' => 1,
        'karbohidrat_100g' => 1,
        'lemak_100g' => 1,
    ]);
}

it('finds an ingredient regardless of letter case', function () {
    addTkpiItem('Bayam, segar (Spinach, fresh)', 16);

    $result = app(TkpiService::class)->searchNutrition('BAYAM');

    expect($result)->toMatchArray([
        'name' => 'Bayam, segar (Spinach, fresh)',
        'calories' => 16.0,
        'data_source' => 'tkpi',
    ]);
});

it('prefers the fresh ingredient over a cooked one', function () {
    addTkpiItem('Bayam, rebus', 23);
    addTkpiItem('Bayam, segar (Spinach, fresh)', 16);

    expect(app(TkpiService::class)->searchNutrition('bayam')['name'])->toBe('Bayam, segar (Spinach, fresh)');
});

it('maps a generic word to the common ingredient', function () {
    addTkpiItem('Telur penyu, segar (Turtle, egg)', 136);
    addTkpiItem('Telur ayam ras, segar (Domestic chicken, egg, fresh)', 154);

    expect(app(TkpiService::class)->searchNutrition('telur')['calories'])->toBe(154.0);
});

it('requires every word of the search to match', function () {
    addTkpiItem('Ikan lemuru, segar (Bali sardinella, fresh)', 112);

    expect(app(TkpiService::class)->searchNutrition('ikan lele'))->toBeNull();
});

it('treats LIKE wildcards in the search as plain text', function (string $search) {
    addTkpiItem('Bayam, segar (Spinach, fresh)', 16);

    expect(app(TkpiService::class)->searchNutrition($search))->toBeNull();
})->with(['%', '_', '   ']);

it('seeds the full TKPI table and can be run again without duplicates', function () {
    $this->seed(TkpiItemsSeeder::class);
    $this->seed(TkpiItemsSeeder::class);

    expect(DB::table('tkpi_items')->count())->toBe(1146)
        ->and(app(TkpiService::class)->searchNutrition('kangkung')['calories'])->toBe(28.0);
});
