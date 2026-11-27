<?php

namespace App\Services;

use App\Exceptions\SupabaseException;
use App\Support\SupabaseSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WasteSummaryService
{

    private const EMOJI = [
        'sayur' => '🥬',
        'buah' => '🍎',
        'daging' => '🍗',
        'ikan' => '🐟',
        'susu_telur' => '🥛',
        'bumbu' => '🧂',
        'kemasan' => '🥫',
        'lainnya' => '🍽️',
    ];

    public function __construct(
        private FoodItemService $foodItems,
        private GeminiSummaryWasteService $geminiService,
    ) {}

    public function forCurrentHousehold(): array
    {
        $householdId = (int) (SupabaseSession::household()['id'] ?? 0);
        $now = Carbon::now('Asia/Jakarta');

        try {
            $rows = $this->foodItems->finished(
                (string) SupabaseSession::accessToken(),
                $householdId,
                $now->copy()->startOfMonth()->subMonths(5),
                $now->copy()->addDay(),
            );
        } catch (SupabaseException $e) {
            Log::warning('Summary load failed: ', ['error' => $e->getMessage()]);

            $stats = $this->compute([], $now);
            $stats['ringkasan'] = 'Ringkasan belum bisa dimuat. Coba lagi sebentar.';

            return $stats;
        }

        $stats = $this->compute($rows, $now);

        return [...$stats, ...$this->narrative($householdId, $stats)];
    }

    private function compute(array $rows, Carbon $now): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->startOfMonth()->subMonths($i)->locale('id');
            $months[$month->format('Y-m')] = ['label' => $month->translatedFormat('M'), 'value' => 0];
        }

        $currentKey = $now->format('Y-m');
        $previousKey = $now->copy()->startOfMonth()->format('Y-m');
        $cooked = 0;
        $thrown = 0;
        $byNameFood = [];

        foreach ($rows as $row) {
            if (empty($row['status_changed_at'])) {
                continue;
            }

            $key = Carbon::parse($row['status_changed_at'])->setTimezone('Asia/Jakarta')->format('Y-m');

            if (! isset($months[$key])) {
                continue;
            }

            if ($row['status'] === 'dimasak') {
                if ($key === $currentKey) {
                    $cooked++;
                }

                continue;
            }

            $initial = max((float) ($row['initial_quantity'] ?? 0), (float) $row['quantity']);
            $fraction = $initial > 0 ? min(1, (float) $row['quantity'] / $initial) : 1;
            $value = (int) round(((int) ($row['prive'] ?? 0)) * $fraction);

            $months[$key]['value'] += $value;

            if ($key !== $currentKey) {
                continue;
            }

            $thrown++;
            $name = trim((string) $row['name']);
            $group = mb_strtolower($name);
            $byNameFood[$group] ??= [
                'name' => $name,
                'emoji' => self::EMOJI[$row['category']] ?? self::EMOJI['lainnya'],
                'count' => 0,
                'value' => 0,
            ];
            $byNameFood[$group]['count']++;
            $byNameFood[$group]['value'] += $value;
        }

        usort($byNameFood, fn (array $a, array $b): int => [$b['value'], $b['count']] <=> [$a['value'], $a['count']]);

        $current = $months[$currentKey]['value'];
        $previous = $months[$previousKey]['value'];
        $finished = $cooked + $thrown;

        return [
            'bulan' => $now->copy()->locale('id')->translatedFormat('F'),
            'terbuang' => $current,
            'perubahan' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : 0,
            'bulan_lalu' => $previous,
            'dimasak' => $cooked,
            'dibuang' => $thrown,
            'terpakai' => $finished > 0 ? (int) round($cooked / $finished * 100) : 0,
            'riwayat' => array_map(fn (array $m): array => [$m['label'], $m['value']], array_values($months)),
            'terbanyak' => array_map(
                fn (array $i): array => [$i['name'], $i['emoji'], $i['count'], $i['value']],
                array_slice($byNameFood, 0, 4),
            ),
            'ringkasan' => '',
            'saran' => [],
        ];
    }

    private function narrative(int $householdId, array $stats): array
    {
        if ($stats['dimasak'] + $stats['dibuang'] === 0) {
            return [
                'ringkasan' => 'Belum ada bahan yang ditandai dimasak atau dibuang bulan ini.',
                'saran' => ['Tandai bahan yang sudah dimasak atau dibuang supaya ringkasan bisa dibuat.'],
            ];
        }

        $payload = [
            'bulan' => $stats['bulan'],
            'terbuang_rupiah' => $stats['terbuang'],
            'bulan_lalu_rupiah' => $stats['bulan_lalu'],
            'perubahan_persen' => $stats['perubahan'],
            'jumlah_dimasak' => $stats['dimasak'],
            'jumlah_dibuang' => $stats['dibuang'],
            'persen_terpakai' => $stats['terpakai'],
            'paling_sering_terbuang' => array_map(
                fn (array $t): array => ['nama' => $t[0], 'kali' => $t[2], 'rupiah' => $t[3]],
                $stats['terbanyak'],
            ),
        ];

        $cacheKey = 'waste-summary:'.$householdId.':'.md5(json_encode($payload));

        try {
            return Cache::remember($cacheKey, now()->addHours(12), fn () => $this->geminiService->summarize($payload));
        } catch (Throwable $e) {
            Log::warning('Waste AI summary failed.', ['error' => $e->getMessage()]);

            return $this->fallback($stats);
        }
    }

    private function fallback(array $stats): array
    {
        $change = abs($stats['perubahan']);

        $headline = match (true) {
            $stats['bulan_lalu'] === 0 => 'Belum ada data bulan lalu untuk dibandingkan.',
            $stats['perubahan'] < 0 => "Pemborosan turun {$change}% dibanding bulan lalu.",
            $stats['perubahan'] > 0 => "Pemborosan naik {$change}% dibanding bulan lalu.",
            default => 'Pemborosan sama seperti bulan lalu.',
        };

        $top = $stats['terbanyak'][0][0] ?? null;

        return [
            'ringkasan' => $top ? "{$headline} {$top} paling sering terbuang." : $headline,
            'saran' => $top ? ["Beli {$top} dalam jumlah lebih sedikit."] : [],
        ];
    }
}
