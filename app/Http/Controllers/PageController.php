<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\HouseholdMembers;
use App\Support\SupabaseSession;
use Illuminate\Http\Request;
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
        $items = DemoData::foodItems();
        $waste = DemoData::waste();

        return view('dashboard', [
            'user' => $this->currentUser(),
            'household' => $this->currentHousehold(),
            'expiring' => $items->filter(fn ($i) => $i['sisa_hari'] <= 3)->values(),
            'stats' => [
                'total' => 24,
                'segera' => $items->filter(fn ($i) => $i['sisa_hari'] <= 3)->count(),
                'lewat' => $items->filter(fn ($i) => $i['sisa_hari'] < 0)->count(),
                'terbuang_short' => str_replace('.', ',', round($waste['terbuang'] / 1000, 1)).'rb',
            ],
            'waste' => $waste,
            'todayMenu' => DemoData::todayMenu(),
            'nutrition' => DemoData::nutrition(),
            'activities' => DemoData::activities(),
        ]);
    }

    public function fridge(Request $request, ?int $item = null): View
    {
        $status = $request->query('status', 'tersedia');
        $kategori = $request->query('kategori');
        $items = DemoData::foodItems()
            ->when($kategori, fn ($c) => $c->where('kategori', $kategori))
            ->when($request->query('q'), fn ($c, $q) => $c->filter(fn ($i) => str_contains(mb_strtolower($i['nama']), mb_strtolower($q))))
            ->sortBy('sisa_hari')
            ->values();

        $selected = $item ? DemoData::foodItem($item) : null;
        abort_if($item && ! $selected, 404);

        return view('fridge.index', [
            'items' => $status === 'tersedia' ? $items : collect(),
            'status' => $status,
            'kategori' => $kategori,
            'selected' => $selected,
            'categories' => DemoData::CATEGORIES,
        ]);
    }

    public function scanProcessing(Request $request): View
    {
        return view('scan.processing', ['jenis' => $request->query('jenis', 'struk')]);
    }

    public function scanFailed(): View
    {
        return view('scan.failed');
    }

    public function scanReview(): View
    {
        return view('scan.review', ['rows' => DemoData::scanResult(), 'categories' => DemoData::CATEGORIES]);
    }

    public function manualInput(): View
    {
        return view('scan.manual', ['categories' => DemoData::CATEGORIES]);
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

    public function shopping(): View
    {
        return view('shopping.index', ['groups' => DemoData::shoppingList()]);
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
        return view('activity.index', ['groups' => DemoData::activities(), 'filter' => $request->query('aksi', 'semua')]);
    }

    public function householdSettings(): View
    {
        return view('household.settings', ['user' => $this->currentUser(), 'household' => $this->currentHousehold()]);
    }

    public function emailPreview(): View
    {
        return view('emails.expiry-reminder', [
            'household' => $this->currentHousehold(),
            'items' => DemoData::foodItems()->filter(fn ($i) => $i['sisa_hari'] >= 0 && $i['sisa_hari'] <= 3)->values(),
            'recipe' => DemoData::recipe(1),
        ]);
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
