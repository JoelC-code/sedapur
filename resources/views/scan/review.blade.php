@php
    $isFridge = $scan instanceof \App\Models\FridgeScan;
    $routePrefix = $isFridge ? 'fridge-scan' : 'scan';

    // Nilai tiap kartu: hasil scan (sudah dinormalisasi job), ditimpa isian lama bila validasi gagal.
    $hasOld = session()->hasOldInput('items');
    $today = now('Asia/Jakarta')->toDateString();
    $rupiah = fn ($value) => 'Rp '.number_format((int) $value, 0, ',', '.');

    $cards = collect($rows)->map(function ($row, $i) use ($hasOld, $today, $units, $categories, $shelfDays, $result) {
        $isFood = (bool) ($row['is_food'] ?? false);
        $category = old("items.$i.category", $row['category'] ?? 'lainnya');
        $category = array_key_exists($category, $categories) ? $category : 'lainnya';
        $unit = old("items.$i.unit", $row['unit'] ?? 'buah');
        $quantity = old("items.$i.quantity", $row['quantity'] ?? 1);
        $unitPrice = old("items.$i.unit_price", $isFridge ? '' : ($row['unit_price'] ?? 0));
        $total = is_numeric($quantity) && is_numeric($unitPrice) ? (int) round($quantity * $unitPrice) : 0;
        $purchasedAt = old("items.$i.purchased_at", $row['purchased_at'] ?? ($result['purchase_date'] ?? $today));
        $defaultExpiry = rescue(fn () => \Illuminate\Support\Carbon::parse($purchasedAt, 'Asia/Jakarta')->addDays($shelfDays[$category])->toDateString(), '', false);
        $expiresAt = old("items.$i.expires_at", $defaultExpiry);

        return [
            'name' => old("items.$i.name", $row['name'] ?? ''),
            'isFood' => $isFood,
            'selected' => $isFood && ($hasOld ? (bool) old("items.$i.selected") : true),
            'quantity' => $quantity,
            'unit' => in_array($unit, $units, true) ? $unit : 'buah',
            'unitPrice' => $unitPrice,
            'total' => $total,
            'warn' => (is_numeric($quantity) && $quantity > 100) || $total > 1_000_000,
            'category' => $category,
            'weight' => old("items.$i.weight_grams", $row['weight_grams'] ?? ''),
            'purchasedAt' => $purchasedAt,
            'expiresAt' => $expiresAt,
            'expiryTouched' => $expiresAt !== $defaultExpiry,
            'nutrition' => $row['nutrition']['data'] ?? null,
        ];
    });

    $selected = $cards->where('selected', true);
    $field = 'rounded-[10px] bg-white px-2.5 py-1.5 focus-within:ring-2 focus-within:ring-olive-700';
    $fieldLabel = 'block text-[10px] font-semibold text-muted';
    $control = 'w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:outline-none';
@endphp
<x-layouts.app title="Cek hasil scan" active="fridge.index" bare>
    {{-- CHANGED: subtitle --}}
    <x-page-header title="Cek hasil scan"
        subtitle="{{ $isFridge ? count($rows).' bahan terdeteksi di kulkas' : count($rows).' item terdeteksi · '.($result['store_name'] ?? 'Toko') }}"
        :back="route('fridge.index')" />

    <div class="lg:grid lg:grid-cols-[320px_1fr] lg:gap-8">
        {{-- CHANGED: panel kiri (desktop) berbeda per jenis scan --}}
        <div class="hidden lg:block">
            <div class="sticky top-6 grid place-items-center rounded-3xl bg-sand p-8">
                @if ($isFridge)
                    <div class="w-full rounded-2xl bg-white p-4 text-sm shadow-lg">
                        <p class="mb-2 flex items-center gap-2 font-bold">
                            <x-icon name="camera" class="size-4 text-olive-700" /> Terdeteksi di kulkas
                        </p>
                        <ul class="space-y-1 text-xs">
                            @foreach ($rows as $row)
                                <li class="flex justify-between gap-2">
                                    <span class="truncate">{{ $row['name'] ?? 'Bahan' }}</span>
                                    <span class="shrink-0 text-muted">{{ $row['quantity'] ?? 1 }} {{ $row['unit'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <p class="mt-6 text-xs text-muted">Foto tersimpan di bucket privat</p>
                @else
                    <div class="w-[230px] -rotate-2 rounded-md bg-white px-[18px] py-4 font-mono text-[11px] leading-[1.7] text-ink shadow-lg">
                        <p class="text-center font-bold">{{ $result['store_name'] ?? 'Toko' }}</p>
                        <hr class="my-1.5 border-dashed border-ink/30">
                        @foreach ($rows as $row)
                            <p class="flex justify-between gap-2">
                                <span class="truncate">{{ $row['name'] ?? 'Barang' }}</span>
                                <span class="shrink-0">{{ $rupiah($row['total_price'] ?? 0) }}</span>
                            </p>
                        @endforeach
                        <hr class="my-1.5 border-dashed border-ink/30">
                        <p class="flex justify-between font-bold">
                            <span>TOTAL</span><span>{{ $rupiah($result['total'] ?? 0) }}</span>
                        </p>
                    </div>
                    <p class="mt-6 text-xs text-muted">Foto tersimpan di bucket privat</p>
                @endif
            </div>
        </div>

        {{-- CHANGED: action mengikuti jenis scan --}}
        <form method="POST" action="{{ route($routePrefix.'.save', $scan) }}" data-receipt-review data-shelf-days='@json($shelfDays)'>
            @csrf
            @if ($errors->any())
                <p role="alert" class="mb-3 rounded-2xl bg-danger-soft p-3 text-xs font-medium text-danger">
                    {{ $errors->first() }}
                </p>
            @endif
            <p class="flex gap-2 rounded-2xl bg-olive-100 p-3 text-xs">
                <x-icon name="sparkles" class="size-4 shrink-0 text-ai" />
                {{-- CHANGED --}}
                @if ($isFridge)
                    Ubah yang salah sebelum disimpan. Jumlah dihitung dari foto, dan tanggal kedaluwarsa hanya perkiraan dari hari ini, jadi cek kemasan.
                @else
                    Ubah yang salah sebelum disimpan. Tanggal kedaluwarsa dari AI adalah perkiraan.
                @endif
            </p>

            <div class="mt-3 space-y-2.5">
                @foreach ($cards as $i => $card)
                    <div data-receipt-item @class([
                        'rounded-2xl p-3',
                        'bg-sand' => $card['isFood'],
                        'opacity-60' => ! $card['selected'],
                        'bg-sand/50' => ! $card['isFood'],
                    ])>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="items[{{ $i }}][selected]" value="1"
                                @checked($card['selected']) @disabled(! $card['isFood'])
                                class="size-5 shrink-0 accent-olive-700"
                                aria-label="Simpan {{ $card['name'] ?: 'bahan' }} ke kulkas">
                            <input type="text" name="items[{{ $i }}][name]" value="{{ $card['name'] }}" maxlength="120"
                                @if ($card['isFood']) data-receipt-required @required($card['selected']) @endif
                                class="min-w-0 flex-1 rounded-md border-0 bg-transparent px-1 py-0.5 text-[15px] font-bold focus:bg-white focus:outline-2 focus:outline-olive-700"
                                aria-label="Nama bahan">
                            <span data-receipt-total class="shrink-0 rounded-full bg-white px-2 py-1 text-[11px] font-bold text-olive-700">
                                {{ $rupiah($card['total']) }}
                            </span>
                        </div>

                        @if ($card['isFood'])
                            <div class="mt-2.5 grid grid-cols-3 gap-2">
                                <label class="{{ $field }}">
                                    <small class="{{ $fieldLabel }}">Jumlah</small>
                                    <input type="number" name="items[{{ $i }}][quantity]" value="{{ $card['quantity'] }}"
                                        step="any" min="0.01" inputmode="decimal" data-receipt-qty
                                        class="{{ $control }} min-w-0">
                                </label>
                                <label class="{{ $field }}">
                                    <small class="{{ $fieldLabel }}">Harga satuan (Rp)</small>
                                    <input type="number" name="items[{{ $i }}][unit_price]" value="{{ $card['unitPrice'] }}"
                                        min="0" step="1" inputmode="numeric" data-receipt-price
                                        class="{{ $control }} min-w-0">
                                </label>
                                <label class="{{ $field }}">
                                    <small class="{{ $fieldLabel }}">Satuan</small>
                                    <select name="items[{{ $i }}][unit]" class="{{ $control }}">
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit }}" @selected($card['unit'] === $unit)>{{ $unit }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="{{ $field }} col-span-3">
                                    <small class="{{ $fieldLabel }}">Kedaluwarsa</small>
                                    <input type="date" name="items[{{ $i }}][expires_at]" value="{{ $card['expiresAt'] }}"
                                        data-receipt-expiry data-receipt-required @required($card['selected'])
                                        @if ($card['expiryTouched']) data-touched="1" @endif
                                        class="{{ $control }}">
                                </label>
                            </div>

                            <p data-receipt-warning @class(['mt-2 text-[11px] font-medium text-warn', 'hidden' => ! $card['warn']])>
                                Angka ini terlihat tidak wajar, cek lagi jumlah atau harga.
                            </p>

                            <details class="group mt-3">
                                <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-2 rounded-xl border border-olive-700/15 bg-white px-3 py-2 text-sm font-bold text-olive-700 shadow-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <x-icon name="chevron-right" class="size-4 shrink-0 transition group-open:rotate-90" />
                                        <span>Detail info</span>
                                        <span class="hidden truncate text-xs font-medium text-muted sm:inline">Kategori, berat, tanggal beli</span>
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold text-olive-700 group-open:hidden">Lihat</span>
                                    <span class="hidden shrink-0 text-xs font-semibold text-olive-700 group-open:inline">Tutup</span>
                                </summary>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <label class="{{ $field }}">
                                        <small class="{{ $fieldLabel }}">Kategori</small>
                                        <select name="items[{{ $i }}][category]" data-receipt-category class="{{ $control }}">
                                            @foreach ($categories as $key => $categoryData)
                                                <option value="{{ $key }}" @selected($card['category'] === $key)>{{ $categoryData['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="{{ $field }}">
                                        <small class="{{ $fieldLabel }}">Berat (g) · opsional</small>
                                        <input type="number" name="items[{{ $i }}][weight_grams]" value="{{ $card['weight'] }}"
                                            min="1" step="any" inputmode="decimal" class="{{ $control }}">
                                    </label>
                                    <label class="{{ $field }}">
                                        <small class="{{ $fieldLabel }}">{{ $isFridge ? 'Tgl dicatat' : 'Tgl beli' }}</small>
                                        <input type="date" name="items[{{ $i }}][purchased_at]" value="{{ $card['purchasedAt'] }}"
                                            data-receipt-purchased class="{{ $control }}">
                                    </label>
                                </div>
                            </details>

                            <section class="mt-3 rounded-xl bg-white p-3 shadow-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-bold">Informasi Gizi</h3>
                                    @if ($card['nutrition'])
                                        <span class="badge bg-ok-soft text-olive-700">{{ strtoupper($card['nutrition']['data_source'] ?? 'Sumber lain') }}</span>
                                    @endif
                                </div>
                                @if ($card['nutrition'])
                                    @php $nutrition = $card['nutrition']; @endphp
                                    <div class="mt-2">
                                        <p class="mb-3 text-xs text-muted">
                                            {{ $nutrition['name'] ?? $card['name'] }}
                                            · Per {{ $nutrition['metric_serving_amount'] ?? 100 }} {{ $nutrition['metric_serving_unit'] ?? 'g' }}
                                        </p>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            @foreach ([['Kalori', 'calories', 'kkal'], ['Protein', 'protein', 'g'], ['Karbohidrat', 'carbohydrates', 'g'], ['Lemak', 'fat', 'g']] as [$label, $key, $suffix])
                                                <div class="rounded-lg bg-sand p-2">
                                                    <p class="text-xs text-muted">{{ $label }}</p>
                                                    <p class="text-base font-bold">
                                                        {{ $nutrition[$key] ?? '-' }} <span class="text-xs font-normal">{{ $suffix }}</span>
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="grid min-h-20 place-items-center text-center text-sm text-muted">
                                        Informasi Gizi tidak ditemukan
                                    </p>
                                @endif
                            </section>
                        @else
                            <p class="mt-1 pl-7 text-[11px] text-muted">Bukan bahan makanan, tidak disimpan.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="sticky bottom-0 -mx-5 mt-6 border-t border-line bg-white px-5 pt-3 pb-5 lg:static lg:mx-0 lg:border-0 lg:px-0">
                {{-- CHANGED: wrapper supaya "hidden" tidak bentrok dengan "flex" --}}
                <div @class(['hidden' => $isFridge])>
                    <div class="mb-3 flex justify-between text-sm">
                        <span class="text-muted">Total belanja</span>
                        <b data-receipt-sum>{{ $rupiah($selected->sum('total')) }}</b>
                    </div>
                </div>
                <button data-receipt-submit class="btn-primary w-full" @disabled($selected->isEmpty())>
                    <span>Simpan <span data-receipt-count>{{ $selected->count() }}</span> bahan ke kulkas</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>