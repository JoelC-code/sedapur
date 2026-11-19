<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\StoreShoppingItemRequest;
use App\Http\Requests\UpdateShoppingItemRequest;
use App\Services\FoodItemService;
use App\Services\ShoppingListService;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Daftar belanja rumah: lihat, tambah manual, centang, hapus.
 */
class ShoppingListController extends Controller
{
    public function __construct(private ShoppingListService $shoppingList) {}

    public function index(): View
    {
        try {
            $items = $this->shoppingList->forHousehold((string) SupabaseSession::accessToken(), SupabaseSession::household()['id']);
        } catch (SupabaseException) {
            return view('shopping.index', ['items' => collect(), 'units' => FoodItemService::UNITS, 'loadFailed' => true]);
        }

        return view('shopping.index', ['items' => collect($items), 'units' => FoodItemService::UNITS, 'loadFailed' => false]);
    }

    public function store(StoreShoppingItemRequest $request): RedirectResponse
    {
        try {
            $this->shoppingList->add(
                (string) SupabaseSession::accessToken(),
                SupabaseSession::household()['id'],
                $request->validated('name'),
                (float) $request->validated('quantity'),
                $request->validated('unit'),
            );
        } catch (SupabaseException) {
            return back()->withInput()->with('status', 'Item belum bisa disimpan. Coba lagi sebentar.');
        }

        return redirect()->route('shopping.index')->with('status', "{$request->validated('name')} masuk ke daftar belanja.");
    }

    public function update(UpdateShoppingItemRequest $request, int $item): RedirectResponse
    {
        $checked = $request->boolean('is_checked');

        try {
            $updated = $this->shoppingList->setChecked(
                (string) SupabaseSession::accessToken(),
                SupabaseSession::household()['id'],
                $item,
                $checked,
                SupabaseSession::user()['id'],
            );
        } catch (SupabaseException) {
            return back()->with('status', 'Centang belum bisa disimpan. Coba lagi sebentar.');
        }

        abort_unless($updated, 404);

        return redirect()->route('shopping.index')->with('status', $checked ? 'Ditandai sudah dibeli.' : 'Centang dibatalkan.');
    }

    public function destroy(int $item): RedirectResponse
    {
        try {
            $removed = $this->shoppingList->remove((string) SupabaseSession::accessToken(), SupabaseSession::household()['id'], $item);
        } catch (SupabaseException) {
            return back()->with('status', 'Item belum bisa dihapus. Coba lagi sebentar.');
        }

        abort_unless($removed, 404);

        return redirect()->route('shopping.index')->with('status', 'Item dihapus dari daftar belanja.');
    }
}
