<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Services\ActivityLogService;
use App\Services\FoodItemService;
use App\Support\DemoData;
use App\Support\HouseholdMembers;
use App\Support\SupabaseSession;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Controller tahap frontend: semua halaman memakai DemoData.
 * Saat integrasi Supabase/Gemini/FatSecret, method di sini diganti
 * untuk mengambil data asli tanpa perlu mengubah view.
 */
class PageController extends Controller
{
    public function dashboard(): View
    {
        $items = $this->availableItems();
        $expiring = $items->filter(fn ($i) => $i['sisa_hari'] !== null && $i['sisa_hari'] <= 3)->values();
        $waste = DemoData::waste();

        return view('dashboard', [
            'user' => $this->currentUser(),
            'household' => $this->currentHousehold(),
            'expiring' => $expiring,
            'stats' => [
                'total' => $items->count(),
                'segera' => $expiring->count(),
                'lewat' => $expiring->filter(fn ($i) => $i['sisa_hari'] < 0)->count(),
                'terbuang_short' => str_replace('.', ',', round($waste['terbuang'] / 1000, 1)).'rb',
            ],
            'waste' => $waste,
            'todayMenu' => DemoData::todayMenu(),
            'nutrition' => DemoData::nutrition(),
            'activities' => $this->recentActivities(null, 4),
        ]);
    }

    public function manualInput(): View
    {
        return view('scan.manual', [
            'item' => null,
            'categories' => DemoData::CATEGORIES,
            'units' => FoodItemService::UNITS,
            'shelfDays' => FoodItemService::SHELF_DAYS,
        ]);
    }

    public function menu(): View
    {
        return view('menu.index', ['plan' => DemoData::weekPlan()]);
    }

    public function recipes(): View
    {
        $recipes = DemoData::recipes();

        return view('recipes.index', [
            'fromFridge' => $recipes->where('dibuat_ai', true)->values(),
            'saved' => $recipes->where('dibuat_ai', false)->values(),
        ]);
    }

    public function recipe(int $recipe): View
    {
        $data = DemoData::recipe($recipe);
        abort_unless($data, 404);

        return view('recipes.show', ['recipe' => $data]);
    }

    public function nutrition(Request $request): View
    {
        return view('nutrition.index', ['tab' => $request->query('tab', 'saya'), 'n' => DemoData::nutrition()]);
    }

    public function waste(): View
    {
        return view('waste.index', ['w' => DemoData::waste()]);
    }

    public function activity(Request $request): View
    {
        $action = in_array($request->query('aksi'), ActivityLogService::ACTIONS, true) ? $request->query('aksi') : null;

        return view('activity.index', ['groups' => $this->recentActivities($action), 'filter' => $action ?? 'semua']);
    }

    public function householdSettings(): View
    {
        return view('household.settings', ['user' => $this->currentUser(), 'household' => $this->currentHousehold()]);
    }

    public function emailPreview(): View
    {
        return view('emails.expiry-reminder', [
            'household' => $this->currentHousehold(),
            'items' => $this->availableItems()->filter(fn ($i) => $i['sisa_hari'] !== null && $i['sisa_hari'] >= 0 && $i['sisa_hari'] <= 3)->values(),
            'recipe' => DemoData::recipe(1),
        ]);
    }

    /**
     * Bahan yang masih tersedia di kulkas rumah aktif, paling cepat kedaluwarsa dulu.
     * Bila Supabase sedang bermasalah, halaman tetap tampil dengan daftar kosong.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function availableItems(): Collection
    {
        try {
            return collect(app(FoodItemService::class)->list(
                (string) SupabaseSession::accessToken(),
                SupabaseSession::household()['id'],
                'tersedia',
                null,
                null,
                array_column(HouseholdMembers::forCurrentHousehold(), 'nama', 'user_id'),
            ));
        } catch (SupabaseException) {
            return collect();
        }
    }

    /**
     * Riwayat aktivitas rumah aktif. Bila Supabase bermasalah, halaman tetap tampil tanpa riwayat.
     *
     * @return list<array{hari: string, items: list<array<int, string>>}>
     */
    private function recentActivities(?string $action, int $limit = 100): array
    {
        try {
            return app(ActivityLogService::class)->recent(
                (string) SupabaseSession::accessToken(),
                SupabaseSession::household()['id'],
                $action,
                array_column(HouseholdMembers::forCurrentHousehold(), 'nama', 'user_id'),
                $limit,
            );
        } catch (SupabaseException) {
            return [];
        }
    }

    /**
     * User yang login, dalam bentuk yang sudah dipakai view (nama, panggilan, email, role).
     *
     * @return array{nama: string, panggilan: string, email: string, role: string}
     */
    private function currentUser(): array
    {
        $user = SupabaseSession::user();
        $name = $user['name'] ?? '';

        return [
            'nama' => $name,
            'panggilan' => strtok($name, ' ') ?: $name,
            'email' => $user['email'] ?? '',
            'role' => SupabaseSession::household()['role'] ?? 'member',
        ];
    }

    /**
     * Rumah aktif dengan anggota asli dari Supabase (nama dari public.profiles).
     *
     * @return array{nama: string, kode_undangan: string, members: list<array<string, mixed>>}
     */
    private function currentHousehold(): array
    {
        $household = SupabaseSession::household();

        return [
            'nama' => $household['name'] ?? '',
            'kode_undangan' => $household['invite_code'] ?? null,
            'members' => HouseholdMembers::forCurrentHousehold(),
        ];
    }
}
