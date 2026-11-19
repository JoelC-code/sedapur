<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Riwayat aktivitas kulkas (public.activity_logs), dibaca lewat Data API dengan token user.
 *
 * Baris riwayat ditulis oleh trigger food_items_log_activity di database, bukan oleh Laravel.
 */
class ActivityLogService
{
    public const ACTIONS = ['ditambah', 'dipakai', 'diubah', 'dimasak', 'dibuang'];

    private const VERBS = [
        'ditambah' => 'menambahkan', 'dipakai' => 'memakai', 'diubah' => 'mengubah',
        'dimasak' => 'menghabiskan', 'dibuang' => 'membuang',
    ];

    public function __construct(private SupabaseService $supabase) {}

    /**
     * Riwayat terbaru, dikelompokkan per hari (WIB), dalam bentuk yang dipakai view riwayat.
     *
     * @param  array<string, string>  $memberNames  user_id => nama
     * @return list<array{hari: string, items: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>}>
     */
    public function recent(string $accessToken, int $householdId, ?string $action, array $memberNames, int $limit = 100): array
    {
        $rows = $this->supabase->select($accessToken, 'activity_logs', array_filter([
            'select' => 'actor_id,action,item_name,description,created_at',
            'household_id' => "eq.{$householdId}",
            'action' => $action !== null ? "eq.{$action}" : null,
            'order' => 'created_at.desc,id.desc',
            'limit' => $limit,
        ]));

        $groups = [];
        foreach ($rows as $row) {
            $at = Carbon::parse($row['created_at'])->timezone('Asia/Jakarta');
            $day = match (true) {
                $at->isToday() => 'Hari ini',
                $at->isYesterday() => 'Kemarin',
                default => $at->translatedFormat('l, j F'),
            };

            $groups[$day][] = [
                $memberNames[$row['actor_id'] ?? ''] ?? ($row['actor_id'] ? 'Mantan anggota' : 'Sistem'),
                (self::VERBS[$row['action']] ?? 'mengubah').' '.$row['item_name'],
                (string) $row['description'],
                $row['action'],
                $at->format('H:i'),
            ];
        }

        return array_map(fn (string $day, array $items): array => ['hari' => $day, 'items' => $items], array_keys($groups), $groups);
    }
}
