<?php

use App\Services\FatSecretService;
use App\Services\FoodItemService;
use App\Services\FridgeNutritionService;
use App\Services\NutritionLookupService;
use App\Services\TkpiService;

function nutritionFoodItem(array $overrides = []): array
{
    return [
        'nama' => 'Beras',
        'kategori' => 'kemasan',
        'jumlah_angka' => 1.5,
        'jumlah_awal_angka' => 2,
        'satuan' => 'kg',
        'berat_gram' => 2000,
        ...$overrides,
    ];
}

function nutritionPer100g(array $overrides = []): array
{
    return [
        'calories' => 360,
        'protein' => 6.8,
        'carbohydrates' => 78.9,
        'fat' => 0.7,
        'data_source' => 'tkpi',
        'metric_serving_unit' => 'g',
        ...$overrides,
    ];
}

it('summarizes available fridge items by category using only FatSecret and TKPI data', function () {
    $items = [
        nutritionFoodItem(),
        nutritionFoodItem([
            'nama' => 'Susu UHT',
            'kategori' => 'susu_telur',
            'jumlah_angka' => 1,
            'jumlah_awal_angka' => 2,
            'satuan' => 'kotak',
            'berat_gram' => 500,
        ]),
        nutritionFoodItem([
            'nama' => 'Kantong plastik',
            'kategori' => 'lainnya',
            'jumlah_angka' => 1,
            'jumlah_awal_angka' => 1,
            'satuan' => 'buah',
            'berat_gram' => null,
        ]),
    ];

    $this->mock(FoodItemService::class)
        ->shouldReceive('list')
        ->once()
        ->with('access-token', 7, 'tersedia', null, null, ['user-1' => 'Jo'])
        ->andReturn($items);

    $lookup = $this->mock(NutritionLookupService::class);
    $lookup->shouldReceive('searchPer100g')
        ->with('Beras')
        ->once()
        ->andReturn(nutritionPer100g());
    $lookup->shouldReceive('searchPer100g')
        ->with('Susu UHT')
        ->once()
        ->andReturn(nutritionPer100g([
            'calories' => 61,
            'protein' => 3.2,
            'carbohydrates' => 4.6,
            'fat' => 3.3,
            'data_source' => 'fatsecret',
        ]));

    $summary = app(FridgeNutritionService::class)->summary('access-token', 7, ['user-1' => 'Jo']);

    expect($summary['jumlah_bahan'])->toBe(3)
        ->and($summary['berat'])->toBe(1750)
        ->and($summary['total'])->toBe(5553)
        ->and($summary['sumber'])->toBe(['fatsecret' => 1, 'tkpi' => 1])
        ->and($summary['kategori'])->toHaveCount(3)
        ->and($summary['kategori'][0]['label'])->toBe('Kemasan')
        ->and($summary['kategori'][0]['kalori'])->toBe(5400)
        ->and($summary['penyumbang'][0]['nama'])->toBe('Beras');
});

it('uses TKPI when FatSecret returns a serving that is not per 100 grams', function () {
    $fatSecret = Mockery::mock(FatSecretService::class);
    $tkpi = Mockery::mock(TkpiService::class);
    $fatSecret->shouldReceive('searchNutrition')->once()->andReturn(nutritionPer100g([
        'data_source' => 'fatsecret',
        'metric_serving_unit' => '1 cup',
    ]));
    $tkpi->shouldReceive('searchNutrition')->once()->with('Susu UHT')->andReturn(nutritionPer100g());

    $nutrition = (new NutritionLookupService($fatSecret, $tkpi))->searchPer100g('Susu UHT');

    expect($nutrition['data_source'])->toBe('tkpi');
});