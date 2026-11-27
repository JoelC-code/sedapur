<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Resep per rumah (public.recipes + public.recipe_ingredients), selalu lewat Data API
 * dengan access token user agar RLS berlaku.
 */
class RecipeService
{
    private const SELECT = 'id,name,description,steps,servings,cook_minutes,calories_per_serving,protein_per_serving,carbs_per_serving,fat_per_serving,is_ai_generated,is_saved,created_at,recipe_ingredients(id,name,amount_text,quantity,unit,grams,sort_order)';

    private const FALLBACK_SERVING_GRAMS = 250.0;

    public function __construct(
        private SupabaseService $supabase,
        private GeminiRecipeService $gemini,
        private ShoppingListService $shoppingList,
    ) {}

    /**
     * Daftar resep rumah, terbaru dulu, dengan pencocokan ketersediaan bahan terhadap kulkas aktif.
     *
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return list<array<string, mixed>>
     */
    public function forHousehold(string $accessToken, int $householdId, ?string $search, array $fridgeItems): array
    {
        $search = trim(str_replace(['*', '%', ',', '(', ')', '"'], '', (string) $search));

        $rows = $this->supabase->select($accessToken, 'recipes', array_filter([
            'select' => self::SELECT,
            'household_id' => "eq.{$householdId}",
            'name' => $search !== '' ? "ilike.*{$search}*" : null,
            'order' => 'created_at.desc',
        ]));

        return array_map(fn (array $row): array => $this->toViewRecipe($row, $fridgeItems), $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return array<string, mixed>|null null bila tidak ditemukan atau bukan milik rumah user (RLS)
     */
    public function find(string $accessToken, int $householdId, int $id, array $fridgeItems): ?array
    {
        $rows = $this->supabase->select($accessToken, 'recipes', [
            'select' => self::SELECT,
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ]);

        return isset($rows[0]) ? $this->toViewRecipe($rows[0], $fridgeItems) : null;
    }

    /**
     * Minta Gemini membuat 3 resep dari stok kulkas lalu simpan ke Supabase.
     *
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return list<int> ID resep yang berhasil disimpan
     */
    public function generateFromFridge(string $accessToken, int $householdId, array $fridgeItems): array
    {
        $generated = $this->gemini->generate($fridgeItems);
        $ids = [];

        foreach ($generated as $recipe) {
            $inserted = $this->supabase->insert($accessToken, 'recipes', [
                'household_id' => $householdId,
                'name' => $recipe['name'],
                'description' => $recipe['description'] !== '' ? $recipe['description'] : null,
                'steps' => $recipe['steps'],
                'servings' => $recipe['servings'],
                'cook_minutes' => $recipe['cook_minutes'],
                'calories_per_serving' => $recipe['calories_per_serving'],
                'protein_per_serving' => $recipe['protein_per_serving'],
                'carbs_per_serving' => $recipe['carbs_per_serving'],
                'fat_per_serving' => $recipe['fat_per_serving'],
                'is_ai_generated' => true,
                'is_saved' => false,
            ], 'id');

            $recipeId = (int) ($inserted['id'] ?? 0);

            if ($recipeId <= 0) {
                continue;
            }

            $ids[] = $recipeId;

            foreach ($recipe['ingredients'] as $index => $ingredient) {
                $this->supabase->insert($accessToken, 'recipe_ingredients', [
                    'recipe_id' => $recipeId,
                    'household_id' => $householdId,
                    'name' => $ingredient['name'],
                    'amount_text' => $ingredient['amount_text'],
                    'quantity' => $ingredient['quantity'],
                    'unit' => $ingredient['unit'],
                    'grams' => $ingredient['grams'],
                    'sort_order' => $index,
                ], 'id');
            }
        }

        return $ids;
    }

    /** @return bool false bila resep tidak ditemukan atau RLS menolak */
    public function setSaved(string $accessToken, int $householdId, int $id, bool $saved): bool
    {
        return $this->supabase->update($accessToken, 'recipes', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ], ['is_saved' => $saved]) > 0;
    }

    /** @return bool false bila resep tidak ditemukan atau RLS menolak */
    public function delete(string $accessToken, int $householdId, int $id): bool
    {
        return $this->supabase->delete($accessToken, 'recipes', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ]) > 0;
    }

    /**
     * Masukkan bahan resep yang belum ada di kulkas ke daftar belanja bersama (hindari duplikat yang belum dibeli).
     *
     * @param  array<string, mixed>  $recipe
     */
    public function addMissingToShoppingList(string $accessToken, int $householdId, array $recipe): int
    {
        $existingNames = array_map(
            fn (array $item): string => mb_strtolower(trim((string) $item['nama'])),
            array_filter(
                $this->shoppingList->forHousehold($accessToken, $householdId),
                fn (array $item): bool => ! $item['dicentang'],
            ),
        );

        $added = 0;

        foreach ($recipe['bahan'] as $ingredient) {
            if ($ingredient['ada']) {
                continue;
            }

            $normalized = mb_strtolower(trim((string) $ingredient['nama']));

            if (in_array($normalized, $existingNames, true)) {
                continue;
            }

            $unit = in_array($ingredient['satuan'], FoodItemService::UNITS, true) ? $ingredient['satuan'] : 'buah';
            $quantity = max(0.01, (float) ($ingredient['jumlah'] ?? 1));

            $this->shoppingList->add($accessToken, $householdId, $ingredient['nama'], $quantity, $unit);
            $existingNames[] = $normalized;
            $added++;
        }

        return $added;
    }

    /**
     * Catat porsi resep yang dimakan ke public.consumption_logs agar langsung muncul di /gizi.
     *
     * @param  array<string, mixed>  $recipe
     */
    public function logConsumption(string $accessToken, int $householdId, array $recipe, float $portions): void
    {
        $portionLabel = match ($portions) {
            0.5 => '½',
            1.5 => '1½',
            default => str_replace('.', ',', (string) $portions),
        };

        $totalGrams = array_sum(array_map(
            fn (array $ingredient): float => (float) ($ingredient['gram'] ?? 0),
            $recipe['bahan'],
        ));
        $servings = max(1, (int) ($recipe['porsi'] ?? 1));
        $servingGrams = $totalGrams > 0 ? $totalGrams / $servings : self::FALLBACK_SERVING_GRAMS;

        $this->supabase->insert($accessToken, 'consumption_logs', [
            'household_id' => $householdId,
            'name' => Str::limit($recipe['nama']." ({$portionLabel} porsi)", 120, ''),
            'grams' => max(1.0, round($servingGrams * $portions, 1)),
            'calories' => round((float) $recipe['kalori'] * $portions, 1),
            'protein_g' => round((float) $recipe['protein'] * $portions, 1),
            'carbs_g' => round((float) $recipe['karbo'] * $portions, 1),
            'fat_g' => round((float) $recipe['lemak'] * $portions, 1),
            'source' => 'estimasi_ai',
        ], 'id');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return array<string, mixed>
     */
    private function toViewRecipe(array $row, array $fridgeItems): array
    {
        $rawIngredients = is_array($row['recipe_ingredients'] ?? null) ? $row['recipe_ingredients'] : [];
        usort($rawIngredients, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        $ingredients = array_map(function (array $ingredient) use ($fridgeItems): array {
            $match = $this->matchFridgeItem((string) $ingredient['name'], $fridgeItems);

            return [
                'id' => (int) ($ingredient['id'] ?? 0),
                'nama' => (string) $ingredient['name'],
                'berat' => (string) ($ingredient['amount_text'] ?? ''),
                'jumlah' => (float) ($ingredient['quantity'] ?? 1),
                'satuan' => (string) ($ingredient['unit'] ?? 'buah'),
                'gram' => $ingredient['grams'] !== null ? (float) $ingredient['grams'] : null,
                'ada' => $match !== null,
                'tag' => $match !== null ? $this->urgencyTag($match['sisa_hari'] ?? null) : null,
            ];
        }, $rawIngredients);

        $steps = is_array($row['steps'] ?? null)
            ? array_values(array_map('strval', $row['steps']))
            : [];

        return [
            'id' => (int) $row['id'],
            'nama' => (string) $row['name'],
            'deskripsi' => (string) ($row['description'] ?? ''),
            'porsi' => (int) ($row['servings'] ?? 2),
            'menit' => (int) ($row['cook_minutes'] ?? 20),
            'kalori' => (int) round((float) ($row['calories_per_serving'] ?? 0)),
            'protein' => round((float) ($row['protein_per_serving'] ?? 0), 1),
            'karbo' => round((float) ($row['carbs_per_serving'] ?? 0), 1),
            'lemak' => round((float) ($row['fat_per_serving'] ?? 0), 1),
            'dibuat_ai' => (bool) ($row['is_ai_generated'] ?? true),
            'tersimpan' => (bool) ($row['is_saved'] ?? false),
            'bahan' => $ingredients,
            'langkah' => $steps,
        ];
    }

    /**
     * Cocokkan nama bahan resep dengan daftar bahan kulkas yang tersedia (paling cepat kedaluwarsa dulu).
     *
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return array<string, mixed>|null
     */
    private function matchFridgeItem(string $ingredientName, array $fridgeItems): ?array
    {
        $needle = mb_strtolower(trim($ingredientName));

        if ($needle === '') {
            return null;
        }

        foreach ($fridgeItems as $item) {
            $haystack = mb_strtolower(trim((string) ($item['nama'] ?? '')));

            if ($haystack === '') {
                continue;
            }

            if ($haystack === $needle || str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                return $item;
            }
        }

        return null;
    }

    private function urgencyTag(?int $sisaHari): ?string
    {
        if ($sisaHari === null || $sisaHari > 3) {
            return null;
        }

        return match (true) {
            $sisaHari < 0 => 'Lewat '.abs($sisaHari).' hari',
            $sisaHari === 0 => 'Hari ini',
            $sisaHari === 1 => 'Besok',
            default => $sisaHari.' hari',
        };
    }
}
