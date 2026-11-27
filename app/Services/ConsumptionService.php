<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Catatan konsumsi pribadi (public.consumption_logs dan view consumption_daily), selalu lewat
 * Data API dengan token user. RLS hanya memperlihatkan catatan milik user itu sendiri.
 */
class ConsumptionService
{
    /** Target harian umum dewasa (AKG); belum ada pengaturan target per user. */
    public const DAILY_TARGET_KCAL = 2000;

    public function __construct(
        private SupabaseService $supabase,
        private NutritionLookupService $nutritionLookup,
        private TkpiService $tkpi,
    ) {}

    /**
     * Ringkasan untuk tab "Konsumsi saya": hari ini, 7 hari terakhir, dan catatan hari ini (WIB).
     *
     * @return array{hari_ini: int, target: int, protein: int, karbo: int, lemak: int, minggu: list<array{0: string, 1: int}>, rata_rata: int, log: list<array<string, mixed>>}
     */
    public function summary(string $accessToken, string $userId): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        $days = array_column($this->supabase->select($accessToken, 'consumption_daily', [
            'select' => 'day,calories,protein_g,carbs_g,fat_g',
            'user_id' => "eq.{$userId}",
            'day' => 'gte.'.$today->copy()->subDays(6)->toDateString(),
        ]), null, 'day');

        $logs = $this->supabase->select($accessToken, 'consumption_logs', [
            'select' => 'id,name,grams,calories,protein_g,carbs_g,fat_g,source,consumed_at',
            'user_id' => "eq.{$userId}",
            'consumed_at' => 'gte.'.$today->toIso8601String(),
            'order' => 'consumed_at.desc',
        ]);

        $now = $days[$today->toDateString()] ?? [];
        $week = array_map(function (int $ago) use ($today, $days): array {
            $day = $today->copy()->subDays($ago);

            return [$day->translatedFormat('D'), (int) round($days[$day->toDateString()]['calories'] ?? 0)];
        }, range(6, 0));
        $loggedDays = array_filter(array_column($week, 1));

        return [
            'hari_ini' => (int) round($now['calories'] ?? 0),
            'target' => self::DAILY_TARGET_KCAL,
            'protein' => (int) round($now['protein_g'] ?? 0),
            'karbo' => (int) round($now['carbs_g'] ?? 0),
            'lemak' => (int) round($now['fat_g'] ?? 0),
            'minggu' => $week,
            'rata_rata' => $loggedDays === [] ? 0 : (int) round(array_sum($loggedDays) / count($loggedDays)),
            'log' => array_map(fn (array $row): array => [
                'id' => $row['id'],
                'nama' => $row['name'],
                'gram' => str_replace('.', ',', (string) (float) $row['grams']),
                'jam' => Carbon::parse($row['consumed_at'])->timezone('Asia/Jakarta')->format('H:i'),
                'kalori' => (int) round($row['calories']),
                'protein' => (float) $row['protein_g'],
                'karbo' => (float) $row['carbs_g'],
                'lemak' => (float) $row['fat_g'],
                'sumber' => $row['source'],
            ], $logs),
        ];
    }

    /**
     * Hitung gizi porsi dari data per 100 g lalu simpan.
     *
     * @return bool false bila data gizi bahan ini tidak ditemukan
     */
    public function log(string $accessToken, int $householdId, string $name, float $grams): bool
    {
        $per100g = $this->per100g($name);

        if ($per100g === null) {
            return false;
        }

        $factor = $grams / 100;

        $this->supabase->insert($accessToken, 'consumption_logs', [
            'household_id' => $householdId,
            'name' => $name,
            'grams' => $grams,
            'calories' => round($per100g['calories'] * $factor, 1),
            'protein_g' => round($per100g['protein'] * $factor, 1),
            'carbs_g' => round($per100g['carbohydrates'] * $factor, 1),
            'fat_g' => round($per100g['fat'] * $factor, 1),
            'source' => $per100g['data_source'],
        ], 'id');

        return true;
    }

    /** @return bool false bila catatan tidak ada atau bukan milik user (RLS) */
    public function delete(string $accessToken, int $id): bool
    {
        return $this->supabase->delete($accessToken, 'consumption_logs', ['id' => "eq.{$id}"]) > 0;
    }

    /**
     * Gizi per 100 g: FatSecret lebih dulu (CLAUDE.md), tapi hanya bila takarannya memang 100 g;
     * takaran lain ("1 cup", "1 medium") tidak bisa dikali gram, jadi jatuh ke TKPI.
     *
     * @return array{calories: float, protein: float, carbohydrates: float, fat: float, data_source: string}|null
     */
    private function per100g(string $name): ?array
    {
        $found = $this->nutritionLookup->search($name)['data'] ?? null;

        if (is_array($found) && ($found['data_source'] === 'tkpi' || preg_match('/^100\s*g$/i', (string) $found['metric_serving_unit']))) {
            return $found;
        }

        return $this->tkpi->searchNutrition($name);
    }
}
