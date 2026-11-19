<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\StoreFoodItemRequest;
use App\Http\Requests\UpdateFoodItemStatusRequest;
use App\Http\Requests\UseFoodItemRequest;
use App\Services\FoodItemService;
use App\Services\TkpiService;
use App\Support\DemoData;
use App\Support\HouseholdMembers;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Isi kulkas rumah: daftar, detail, tambah manual, tandai dimasak/dibuang.
 */
class FridgeController extends Controller
{
    private const STATUSES = ['tersedia', 'dimasak', 'dibuang'];

    public function __construct(private FoodItemService $foodItems) {}

    public function index(Request $request, TkpiService $tkpi, ?int $item = null): View
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'tersedia';
        $kategori = array_key_exists((string) $request->query('kategori'), DemoData::CATEGORIES) ? $request->query('kategori') : null;
        $names = array_column(HouseholdMembers::forCurrentHousehold(), 'nama', 'user_id');
        $householdId = SupabaseSession::household()['id'];
        $token = (string) SupabaseSession::accessToken();

        try {
            $items = collect($this->foodItems->list($token, $householdId, $status, $kategori, $request->query('q'), $names));
            $selected = $item ? $this->foodItems->find($token, $householdId, $item, $names) : null;
        } catch (SupabaseException) {
            return view('fridge.index', [
                'items' => collect(), 'status' => $status, 'kategori' => $kategori, 'selected' => null,
                'categories' => DemoData::CATEGORIES, 'loadFailed' => true,
            ]);
        }

        abort_if($item && ! $selected, 404);

        if ($selected) {
            $selected['gizi'] = $tkpi->searchNutrition($selected['nama']);
        }

        return view('fridge.index', [
            'items' => $items,
            'status' => $status,
            'kategori' => $kategori,
            'selected' => $selected,
            'categories' => DemoData::CATEGORIES,
            'loadFailed' => false,
        ]);
    }

    public function store(StoreFoodItemRequest $request): RedirectResponse
    {
        try {
            $id = $this->foodItems->create((string) SupabaseSession::accessToken(), SupabaseSession::household()['id'], $request->validated());
        } catch (SupabaseException) {
            return back()->withInput()->with('status', 'Bahan belum bisa disimpan. Coba lagi sebentar.');
        }

        return redirect()->route('fridge.show', $id)->with('status', "{$request->validated('name')} masuk ke kulkas.");
    }

    public function edit(int $item): View
    {
        try {
            $data = $this->foodItems->find((string) SupabaseSession::accessToken(), SupabaseSession::household()['id'], $item, []);
        } catch (SupabaseException) {
            abort(503, 'Bahan belum bisa dimuat. Coba lagi sebentar.');
        }

        abort_unless($data, 404);

        return view('scan.manual', [
            'item' => $data,
            'categories' => DemoData::CATEGORIES,
            'units' => FoodItemService::UNITS,
            'shelfDays' => FoodItemService::SHELF_DAYS,
        ]);
    }

    public function update(StoreFoodItemRequest $request, int $item): RedirectResponse
    {
        try {
            $updated = $this->foodItems->update((string) SupabaseSession::accessToken(), SupabaseSession::household()['id'], $item, $request->validated());
        } catch (SupabaseException) {
            return back()->withInput()->with('status', 'Perubahan belum bisa disimpan. Coba lagi sebentar.');
        }

        abort_unless($updated, 404);

        return redirect()->route('fridge.show', $item)->with('status', 'Perubahan disimpan.');
    }

    public function use(UseFoodItemRequest $request, int $item): RedirectResponse
    {
        try {
            $result = $this->foodItems->use((string) SupabaseSession::accessToken(), $item, (float) $request->validated('amount'));
        } catch (SupabaseException) {
            return back()->with('status', 'Pemakaian belum bisa dicatat. Coba lagi sebentar.');
        }

        abort_unless($result, 404);

        return $result['status'] === 'tersedia'
            ? redirect()->route('fridge.show', $item)->with('status', 'Pemakaian dicatat.')
            : redirect()->route('fridge.index')->with('status', 'Bahan habis dipakai dan ditandai dimasak.');
    }

    public function updateStatus(UpdateFoodItemStatusRequest $request, int $item): RedirectResponse
    {
        $status = $request->validated('status');

        try {
            $updated = $this->foodItems->setStatus((string) SupabaseSession::accessToken(), SupabaseSession::household()['id'], $item, $status);
        } catch (SupabaseException) {
            return back()->with('status', 'Status bahan belum bisa diubah. Coba lagi sebentar.');
        }

        abort_unless($updated, 404);

        return redirect()->route('fridge.index')->with('status', $status === 'dibuang' ? 'Bahan ditandai dibuang.' : 'Bahan ditandai dimasak.');
    }
}
