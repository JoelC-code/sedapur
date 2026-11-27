<?php

use App\Models\FridgeScan;
use App\Models\ReceiptScan;
use App\Services\FoodItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([
            ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
        ]),
        'test.supabase.co/rest/v1/food_items*' => Http::response([['id' => 21]], 201),
    ]);
});

/** Scan selesai milik user di supabaseSession(): satu bahan makanan dan satu barang non-makanan. */
function completedScan(string $userId = '00000000-0000-4000-8000-00000000000a'): ReceiptScan
{
    return ReceiptScan::create([
        'user_id' => $userId,
        'household_id' => 1,
        'status' => 'completed',
        'result' => [
            'store_name' => 'Toko Maju',
            'purchase_date' => '2026-11-19',
            'total' => 18000,
            'items' => [
                ['name' => 'Bayam hijau', 'quantity' => 2, 'unit' => 'ikat', 'unit_price' => 3000, 'total_price' => 6000, 'is_food' => true, 'nutrition' => null],
                ['name' => 'Sabun cuci', 'quantity' => 1, 'unit' => 'buah', 'unit_price' => 12000, 'total_price' => 12000, 'is_food' => false, 'nutrition' => null],
            ],
        ],
    ]);
}

function completedFridgeScan(string $userId = '00000000-0000-4000-8000-00000000000a'): FridgeScan
{
    return FridgeScan::create([
        'user_id' => $userId,
        'household_id' => 1,
        'status' => 'completed',
        'result' => [
            'items' => [
                ['name' => 'Beras', 'quantity' => 2, 'unit' => 'kg', 'is_food' => true, 'nutrition' => null],
            ],
        ],
    ]);
}

/** Isian form untuk bahan makanan (indeks 0) yang dicentang. */
function scannedFoodInput(array $overrides = []): array
{
    return [
        'selected' => '1', 'name' => 'Bayam hijau', 'quantity' => '2', 'unit_price' => '3000', 'unit' => 'ikat',
        'category' => 'sayur', 'purchased_at' => '2026-11-19', 'expires_at' => '2026-11-24',
        ...$overrides,
    ];
}

it('shows scanned items with the unit list and marks non-food items', function () {
    $response = $this->withSession(supabaseSession())->get(route('scan.review', completedScan()))
        ->assertOk()
        ->assertSee('value="Bayam hijau"', false)
        ->assertSee('name="items[0][purchased_at]" value="2026-11-19"', false)
        ->assertSee('Bukan bahan makanan');

    foreach (FoodItemService::UNITS as $unit) {
        $response->assertSee('<option value="'.$unit.'"', false);
    }
});

it('shows price, expiry, details and an empty nutrition card for fridge scans', function () {
    $this->withSession(supabaseSession())
        ->get(route('fridge-scan.review', completedFridgeScan()))
        ->assertOk()
        ->assertSee('Jumlah')
        ->assertSee('Harga satuan (Rp)')
        ->assertSee('Satuan')
        ->assertSee('Kedaluwarsa')
        ->assertSee('Detail info')
        ->assertSee('Informasi Gizi')
        ->assertSee('Informasi Gizi tidak ditemukan');
});

it('saves the entered unit price and purchase date for fridge scans', function () {
    $scan = completedFridgeScan();

    $this->withSession(supabaseSession())
        ->post(route('fridge-scan.save', $scan), ['items' => [[
            'selected' => '1',
            'name' => 'Beras',
            'quantity' => '2',
            'unit_price' => '2500',
            'unit' => 'kg',
            'category' => 'lainnya',
            'purchased_at' => '2026-11-19',
            'expires_at' => '2026-11-26',
        ]]])
        ->assertRedirect(route('fridge.index'))
        ->assertSessionHas('status', '1 bahan berhasil disimpan ke kulkas.');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/food_items')
        && $request['name'] === 'Beras'
        && $request['price'] === 5000
        && $request['purchased_at'] === '2026-11-19'
        && $request['input_source'] === 'scan_kulkas');
});

it('saves a checked food item to the household fridge as a scanned item', function () {
    $scan = completedScan();

    $this->withSession(supabaseSession())
        ->post(route('scan.save', $scan), ['items' => [scannedFoodInput()]])
        ->assertRedirect(route('fridge.index'))
        ->assertSessionHas('status', '1 bahan berhasil disimpan ke kulkas.');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/rest/v1/food_items')
        && $request->hasHeader('Authorization', 'Bearer user-access-token')
        && $request['household_id'] === 1
        && $request['name'] === 'Bayam hijau'
        && $request['price'] === 6000
        && $request['input_source'] === 'scan_struk');
    Http::assertSentCount(1);
});

it('never sends non-food or unchecked items', function () {
    $scan = completedScan();

    $this->withSession(supabaseSession())
        ->from(route('scan.review', $scan))
        ->post(route('scan.save', $scan), ['items' => [
            scannedFoodInput(['selected' => null]),
            ['selected' => '1', 'name' => 'Sabun cuci', 'quantity' => '1', 'unit_price' => '12000', 'unit' => 'buah', 'expires_at' => '2026-12-01'],
        ]])
        ->assertRedirect(route('scan.review', $scan))
        ->assertSessionHasErrors('items')
        ->assertSessionHasInput('items.0.name', 'Bayam hijau');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/rest/v1/food_items'));
});

it('keeps the edits of the user after a validation error', function () {
    $this->withSession([...supabaseSession(), '_old_input' => ['items' => [scannedFoodInput(['name' => 'Bayam merah', 'quantity' => '1.5', 'selected' => null])]]])
        ->get(route('scan.review', completedScan()))
        ->assertOk()
        ->assertSee('value="Bayam merah"', false)
        ->assertSee('value="1.5"', false)
        ->assertSee('Simpan <span data-receipt-count>0</span> bahan', false);
});

it('forbids viewing or saving a scan that belongs to another user', function () {
    $scan = completedScan('00000000-0000-4000-8000-0000000000bb');

    $this->withSession(supabaseSession())->get(route('scan.review', $scan))->assertForbidden();
    $this->withSession(supabaseSession())->post(route('scan.save', $scan), ['items' => [scannedFoodInput()]])->assertForbidden();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/rest/v1/food_items'));
});
