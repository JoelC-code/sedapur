<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Analitik food waste dari view food_waste_monthly dan food_waste_items (security_invoker,
 * jadi RLS food_items tetap berlaku), dibaca lewat Data API dengan token user.
 */
class FoodWasteService
{
    private const MONTHS = 6;

    public function __construct(private SupabaseService $supabase) {}

    /**
     * Ringkasan bulan ini, bulan lalu, dan 6 bulan terakhir (WIB).
     *
     * @return array{bulan: string, bulan_lalu_nama: string, terbuang: int, bulan_lalu: int, perubahan: int|null, dimasak: int, dibuang: int, terpakai: int|null, riwayat: list<array{0: string, 1: int}>, terbanyak: list<array{0: string, 1: int, 2: int}>}
     */
    public function summary(string $accessToken, int $householdId): array
    {
        $thisMonth = Carbon::now('Asia/Jakarta')->startOfMonth();

        $monthly = $this->supabase->select($accessToken, 'food_waste_monthly', [
            'select' => 'month,cooked_count,wasted_count,wasted_value',
            'household_id' => "eq.{$householdId}",
            'month' => 'gte.'.$thisMonth->copy()->subMonths(self::MONTHS - 1)->toDateString(),
        ]);

        $topItems = $this->supabase->select($accessToken, 'food_waste_items', [
            'select' => 'name,wasted_count,wasted_value',
            'household_id' => "eq.{$householdId}",
            'month' => 'eq.'.$thisMonth->toDateString(),
            'order' => 'wasted_value.desc,wasted_count.desc',
            'limit' => 4,
        ]);

        return $this->build($thisMonth, $monthly, $topItems);
    }

    /**
     * Ringkasan kosong, dipakai bila Supabase sedang bermasalah.
     *
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return $this->build(Carbon::now('Asia/Jakarta')->startOfMonth(), [], []);
    }

    /**
     * @param  list<array<string, mixed>>  $monthly
     * @param  list<array<string, mixed>>  $topItems
     * @return array<string, mixed>
     */
    private function build(Carbon $thisMonth, array $monthly, array $topItems): array
    {
        $byMonth = array_column($monthly, null, 'month');
        $month = fn (Carbon $m): array => $byMonth[$m->toDateString()] ?? ['cooked_count' => 0, 'wasted_count' => 0, 'wasted_value' => 0];

        $now = $month($thisMonth);
        $last = $month($thisMonth->copy()->subMonth());
        $wasted = (int) $now['wasted_value'];
        $lastWasted = (int) $last['wasted_value'];
        $finished = (int) $now['cooked_count'] + (int) $now['wasted_count'];

        return [
            'bulan' => $thisMonth->translatedFormat('F'),
            'bulan_lalu_nama' => $thisMonth->copy()->subMonth()->translatedFormat('F'),
            'terbuang' => $wasted,
            'bulan_lalu' => $lastWasted,
            'perubahan' => $lastWasted > 0 ? (int) round(($wasted - $lastWasted) / $lastWasted * 100) : null,
            'dimasak' => (int) $now['cooked_count'],
            'dibuang' => (int) $now['wasted_count'],
            'terpakai' => $finished > 0 ? (int) round($now['cooked_count'] / $finished * 100) : null,
            'riwayat' => array_map(function (int $ago) use ($thisMonth, $month): array {
                $m = $thisMonth->copy()->subMonths($ago);

                return [$m->translatedFormat('M'), (int) $month($m)['wasted_value']];
            }, range(self::MONTHS - 1, 0)),
            'terbanyak' => array_map(fn (array $item): array => [
                $item['name'], (int) $item['wasted_count'], (int) $item['wasted_value'],
            ], $topItems),
        ];
    }
}
