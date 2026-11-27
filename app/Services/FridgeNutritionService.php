<?php

namespace App\Services;

use App\Support\DemoData;

class FridgeNutritionService
{
    public function __construct(
        private FoodItemService $foodItems,
        private NutritionLookupService $nutritionLookup,
    ) {}

    /**
     * @param  array<string, string>  $memberNames
     * @return array<string, mixed>
     */
    public function summary(string $accessToken, int $householdId, array $memberNames): array
    {
        $items = $this->foodItems->list($accessToken, $householdId, 'tersedia', null, null, $memberNames);
        $categories = [];
        $sources = ['fatsecret' => 0, 'tkpi' => 0];
        $contributors = [];
        $totals = ['kalori' => 0.0, 'protein' => 0.0, 'karbo' => 0.0, 'lemak' => 0.0, 'berat' => 0.0];

        foreach ($items as $item) {
            $categoryKey = $item['kategori'];
            $category = DemoData::CATEGORIES[$categoryKey] ?? DemoData::CATEGORIES['lainnya'];
            $categories[$categoryKey] ??= [
                'label' => $category['label'],
                'emoji' => $category['emoji'],
                'jumlah_bahan' => 0,
                'kalori' => 0.0,
                'protein' => 0.0,
                'karbo' => 0.0,
                'lemak' => 0.0,
            ];
            $categories[$categoryKey]['jumlah_bahan']++;

            $grams = $this->weightInGrams($item);
            if ($grams === null) {
                continue;
            }

            $totals['berat'] += $grams;
            $nutrition = $this->nutritionLookup->searchPer100g($item['nama']);
            if (! $nutrition || ! in_array($nutrition['data_source'] ?? null, ['fatsecret', 'tkpi'], true)) {
                continue;
            }

            $factor = $grams / 100;
            $values = [
                'kalori' => (float) ($nutrition['calories'] ?? 0) * $factor,
                'protein' => (float) ($nutrition['protein'] ?? 0) * $factor,
                'karbo' => (float) ($nutrition['carbohydrates'] ?? 0) * $factor,
                'lemak' => (float) ($nutrition['fat'] ?? 0) * $factor,
            ];

            foreach ($values as $key => $value) {
                $totals[$key] += $value;
                $categories[$categoryKey][$key] += $value;
            }

            $sources[$nutrition['data_source']]++;
            $contributors[] = [
                'nama' => $item['nama'],
                'berat' => $grams,
                'kalori' => $values['kalori'],
            ];
        }

        foreach ($categories as &$category) {
            foreach (['kalori', 'protein', 'karbo', 'lemak'] as $key) {
                $category[$key] = (int) round($category[$key]);
            }
        }
        unset($category);

        usort($contributors, fn (array $a, array $b): int => $b['kalori'] <=> $a['kalori']);
        $contributors = array_slice(array_map(fn (array $item): array => [
            ...$item,
            'berat' => number_format($item['berat'], 0, ',', '.').' g',
            'kalori' => (int) round($item['kalori']),
        ], $contributors), 0, 3);

        return [
            'total' => (int) round($totals['kalori']),
            'jumlah_bahan' => count($items),
            'protein' => (int) round($totals['protein']),
            'karbo' => (int) round($totals['karbo']),
            'lemak' => (int) round($totals['lemak']),
            'berat' => (int) round($totals['berat']),
            'sumber' => $sources,
            'kategori' => array_values($categories),
            'penyumbang' => $contributors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return [
            'total' => 0,
            'jumlah_bahan' => 0,
            'protein' => 0,
            'karbo' => 0,
            'lemak' => 0,
            'berat' => 0,
            'sumber' => ['fatsecret' => 0, 'tkpi' => 0],
            'kategori' => [],
            'penyumbang' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function weightInGrams(array $item): ?float
    {
        $quantity = (float) ($item['jumlah_angka'] ?? 0);

        if ($quantity <= 0) {
            return null;
        }

        if ($item['satuan'] === 'g') {
            return $quantity;
        }

        if ($item['satuan'] === 'kg') {
            return $quantity * 1000;
        }

        $initialQuantity = (float) ($item['jumlah_awal_angka'] ?? $quantity);
        $initialWeight = (float) ($item['berat_gram'] ?? 0);

        if ($initialQuantity <= 0 || $initialWeight <= 0) {
            return null;
        }

        return $initialWeight * $quantity / $initialQuantity;
    }
}