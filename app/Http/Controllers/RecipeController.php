<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\LogRecipePortionRequest;
use App\Http\Requests\UpdateRecipeSavedRequest;
use App\Services\FoodItemService;
use App\Services\RecipeService;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Daftar resep dan detail resep rumah: buat dengan Gemini dari isi kulkas, simpan, hapus,
 * tambahkan bahan yang belum ada ke daftar belanja, dan catat porsi dimakan ke /gizi.
 */
class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipes,
        private FoodItemService $foodItems,
    ) {}

    public function index(Request $request): View
    {
        $token = (string) SupabaseSession::accessToken();
        $householdId = (int) SupabaseSession::household()['id'];
        $search = trim((string) $request->query('q', ''));
        $fridgeItems = $this->availableFridgeItems($token, $householdId);

        try {
            $all = collect($this->recipes->forHousehold($token, $householdId, $search, $fridgeItems));
            $loadFailed = false;
        } catch (SupabaseException) {
            [$all, $loadFailed] = [collect(), true];
        }

        return view('recipes.index', [
            'fromFridge' => $all->where('tersimpan', false)->values(),
            'saved' => $all->where('tersimpan', true)->values(),
            'search' => $search,
            'fridgeCount' => count($fridgeItems),
            'loadFailed' => $loadFailed,
        ]);
    }

    public function show(int $recipe): View
    {
        $token = (string) SupabaseSession::accessToken();
        $householdId = (int) SupabaseSession::household()['id'];
        $fridgeItems = $this->availableFridgeItems($token, $householdId);

        try {
            $data = $this->recipes->find($token, $householdId, $recipe, $fridgeItems);
        } catch (SupabaseException) {
            abort(503, 'Resep belum bisa dimuat. Coba lagi sebentar.');
        }

        abort_unless($data, 404);

        return view('recipes.show', ['recipe' => $data]);
    }

    public function generate(): RedirectResponse
    {
        $token = (string) SupabaseSession::accessToken();
        $householdId = (int) SupabaseSession::household()['id'];
        $fridgeItems = $this->availableFridgeItems($token, $householdId);

        if ($fridgeItems === []) {
            return redirect()->route('recipes.index')
                ->with('status', 'Tambahkan minimal 1 bahan ke kulkas dulu sebelum membuat resep dengan AI.');
        }

        try {
            $ids = $this->recipes->generateFromFridge($token, $householdId, $fridgeItems);
        } catch (RuntimeException $e) {
            return redirect()->route('recipes.index')->with('status', $e->getMessage());
        } catch (SupabaseException) {
            return redirect()->route('recipes.index')->with('status', 'Resep belum bisa disimpan. Coba lagi sebentar.');
        }

        return redirect()->route('recipes.index')
            ->with('status', count($ids).' resep baru dibuat dari isi kulkasmu.');
    }

    public function updateSaved(UpdateRecipeSavedRequest $request, int $recipe): RedirectResponse
    {
        $saved = $request->boolean('is_saved');

        try {
            $updated = $this->recipes->setSaved(
                (string) SupabaseSession::accessToken(),
                (int) SupabaseSession::household()['id'],
                $recipe,
                $saved,
            );
        } catch (SupabaseException) {
            return back()->with('status', 'Perubahan belum bisa disimpan. Coba lagi sebentar.');
        }

        abort_unless($updated, 404);

        return redirect()->route('recipes.show', $recipe)
            ->with('status', $saved ? 'Resep disimpan ke daftar tersimpan.' : 'Resep dihapus dari daftar tersimpan.');
    }

    public function destroy(int $recipe): RedirectResponse
    {
        try {
            $deleted = $this->recipes->delete(
                (string) SupabaseSession::accessToken(),
                (int) SupabaseSession::household()['id'],
                $recipe,
            );
        } catch (SupabaseException) {
            return back()->with('status', 'Resep belum bisa dihapus. Coba lagi sebentar.');
        }

        abort_unless($deleted, 404);

        return redirect()->route('recipes.index')->with('status', 'Resep dihapus.');
    }

    public function addMissingToShoppingList(int $recipe): RedirectResponse
    {
        $token = (string) SupabaseSession::accessToken();
        $householdId = (int) SupabaseSession::household()['id'];
        $fridgeItems = $this->availableFridgeItems($token, $householdId);

        try {
            $data = $this->recipes->find($token, $householdId, $recipe, $fridgeItems);
            abort_unless($data, 404);

            $added = $this->recipes->addMissingToShoppingList($token, $householdId, $data);
        } catch (SupabaseException) {
            return back()->with('status', 'Bahan belum bisa ditambahkan ke daftar belanja. Coba lagi sebentar.');
        }

        $message = $added > 0
            ? "{$added} bahan ditambahkan ke daftar belanja."
            : 'Semua bahan yang belum ada sudah masuk daftar belanja.';

        return redirect()->route('recipes.show', $recipe)->with('status', $message);
    }

    public function logPortion(LogRecipePortionRequest $request, int $recipe): RedirectResponse
    {
        $token = (string) SupabaseSession::accessToken();
        $householdId = (int) SupabaseSession::household()['id'];
        $fridgeItems = $this->availableFridgeItems($token, $householdId);

        try {
            $data = $this->recipes->find($token, $householdId, $recipe, $fridgeItems);
            abort_unless($data, 404);

            $this->recipes->logConsumption($token, $householdId, $data, (float) $request->validated('portions'));
        } catch (SupabaseException) {
            return back()->with('status', 'Porsi belum bisa dicatat. Coba lagi sebentar.');
        }

        return redirect()->route('recipes.show', $recipe)->with('status', 'Porsi dimakan dicatat ke Gizi & kalori.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableFridgeItems(string $token, int $householdId): array
    {
        try {
            return $this->foodItems->list($token, $householdId, 'tersedia', null, null, []);
        } catch (SupabaseException) {
            return [];
        }
    }
}
