<x-layouts.app title="Cek hasil scan" active="fridge.index" bare>
    <x-page-header title="Cek hasil scan"
        subtitle="{{ count($rows) }} item terdeteksi · {{ $result['store_name'] ?? 'Toko' }}" :back="route('fridge.index')" />

    <div class="lg:grid lg:grid-cols-[320px_1fr] lg:gap-8">
        {{-- Foto struk (desktop) --}}
        <div class="hidden lg:block">
            <div class="sticky top-6 grid place-items-center rounded-3xl bg-sand p-8">
                <div
                    class="w-[230px] -rotate-2 rounded-md bg-[#fcfcfa] px-[18px] py-4 font-mono text-[11px] leading-[1.7] text-[#3a3a3a] shadow-[0_8px_24px_rgba(0,0,0,.18)]">
                    <p class="text-center font-bold">{{ $result['store_name'] ?? 'Toko' }}</p>
                    <hr class="my-1.5 border-dashed border-black/30">
                    @foreach ($rows as $i => $row)
                        <p class="flex justify-between">
                            <span>{{ $row['name'] ?? 'Unknown Items' }}</span>
                            @php
                                $receiptTotal = \App\Support\ReceiptPriceNormalizer::lineTotal(
                                    $row['quantity'] ?? null,
                                    $row['unit_price'] ?? null
                                );
                                if ($receiptTotal === 0) {
                                    $receiptTotal = \App\Support\ReceiptPriceNormalizer::receiptTotal($row['total_price'] ?? 0);
                                }
                            @endphp
                            <span>Rp {{ number_format($receiptTotal, 0, ',', '.') }}</span>
                        </p>
                    @endforeach
                    <hr class="my-1.5 border-dashed border-black/30">
                    <p class="flex justify-between font-bold">
                        <span>TOTAL</span><span>Rp {{ number_format($result['total'] ?? 0, 0, ',', '.') }}</span></p>
                </div>
                <p class="mt-6 text-xs text-muted">Foto tersimpan di bucket privat</p>
            </div>
        </div>

        <form method="POST" action="{{ route('scan.save', $scan) }}">
            @csrf
            @if ($errors->any())
                <p class="mb-3 rounded-2xl bg-red-50 p-3 text-xs text-brand">
                    {{ $errors->first() }}
                </p>
            @endif
            <p class="flex gap-2 rounded-2xl bg-olive-100 p-3 text-xs">
                <x-icon name="sparkles" class="size-4 shrink-0 text-ai" />
                Ubah yang salah sebelum disimpan. Tanggal kedaluwarsa dari AI adalah perkiraan.
            </p>

            <div class="mt-3 space-y-2.5">
                @foreach ($rows as $i => $row)
                    @php
                        $isFood = $row['is_food'] ?? false;
                        $nutrition = $row['nutrition']['data'] ?? null;
                        $unitPrice = \App\Support\ReceiptPriceNormalizer::unitPrice($row['unit_price'] ?? 0);
                        $unit = \App\Support\ReceiptPriceNormalizer::unit($row['unit'] ?? 'buah');
                        $itemTotal = \App\Support\ReceiptPriceNormalizer::lineTotal(
                            $row['quantity'] ?? null,
                            $unitPrice
                        );
                        $purchasedAt = now('Asia/Jakarta')->toDateString();
                        $category = $row['category'] ?? 'lainnya';
                        $category = array_key_exists($category, \App\Support\DemoData::CATEGORIES)
                            ? $category
                            : 'lainnya';
                        $shelfDays = \App\Services\FoodItemService::SHELF_DAYS[$category] ?? 7;
                        $expiresAt = \Illuminate\Support\Carbon::createFromFormat(
                            'Y-m-d',
                            $purchasedAt,
                            'Asia/Jakarta'
                        )->addDays($shelfDays)->toDateString();
                    @endphp
                    <div data-receipt-item="{{ $i }}" @class([
                        'rounded-2xl p-2.5',
                        'bg-sand' => $isFood,
                        'bg-sand/50 opacity-60' => !$isFood,
                    ])>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="items[{{ $i }}][selected]" value="1"
                                @checked($isFood)
                                @disabled(!$isFood)
                                class="receipt-item-checkbox size-4 rounded border-2 border-sand-dark text-olive-700 focus:ring-olive-700"
                                aria-label="Simpan {{ $row['name'] ?? 'bahan' }} ke kulkas">
                            <input type="text" name="items[{{ $i }}][name]"
                                value="{{ $row['name'] ?? 'Noname' }}"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] font-bold focus:ring-0"
                                aria-label="Nama bahan">
                            <span id="item-total-{{ $i }}" class="shrink-0 rounded-full bg-white px-2 py-1 text-[11px] font-bold text-olive-700">
                                Rp {{ number_format($itemTotal, 0, ',', '.') }}
                            </span>
                            <x-icon name="edit" class="size-4 text-muted" />
                        </div>

                        @if ($isFood)
                            {{-- 1. Quantity, price and unit --}}
                            <div class="mt-2.5 grid grid-cols-3 gap-2">
                                @foreach ([['Jumlah', 'quantity', $row['quantity'] ?? 1], ['Harga Satuan (Rp)', 'unit_price', $unitPrice]] as [$label, $field, $value])
                                    <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                        <small class="block text-[10px] font-semibold text-muted">
                                            {{ $label }}
                                        </small>
                                        <input name="items[{{ $i }}][{{ $field }}]"
                                            value="{{ $value }}"
                                            class="{{ $field === 'unit_price' ? 'receipt-price-input ' : '' }}{{ $field === 'quantity' ? 'receipt-quantity-input ' : '' }}w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0"
                                            @if ($field === 'unit_price') inputmode="numeric" @endif>
                                    </label>
                                @endforeach
                                <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                    <small class="block text-[10px] font-semibold text-muted">
                                        Satuan
                                    </small>
                                    <select name="items[{{ $i }}][unit]"
                                        class="w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                        @foreach (['g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak'] as $unitOption)
                                            <option value="{{ $unitOption }}" @selected($unit === $unitOption)>
                                                {{ $unitOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="mt-2.5 grid grid-cols-2 gap-2">
                                <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                    <small class="block text-[10px] font-semibold text-muted">
                                        Kategori
                                    </small>
                                    <select name="items[{{ $i }}][category]"
                                        class="receipt-category-input w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                        @foreach (\App\Support\DemoData::CATEGORIES as $categoryOption => $categoryData)
                                            <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>
                                                {{ $categoryData['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                    <small class="block text-[10px] font-semibold text-muted">
                                        Berat (g) · opsional
                                    </small>
                                    <input type="number" name="items[{{ $i }}][weight_grams]"
                                        value="{{ $row['weight_grams'] ?? '' }}"
                                        min="1" step="any" inputmode="decimal"
                                        class="w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                </label>
                                <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                    <small class="block text-[10px] font-semibold text-muted">
                                        Tgl beli
                                    </small>
                                    <input type="date" name="items[{{ $i }}][purchased_at]"
                                        value="{{ $purchasedAt }}"
                                        class="receipt-purchased-input w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                </label>
                                <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                    <small class="block text-[10px] font-semibold text-muted">
                                        Kedaluwarsa
                                    </small>
                                    <input type="date" name="items[{{ $i }}][expires_at]"
                                        value="{{ $expiresAt }}"
                                        class="receipt-expiry-input w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0"
                                        required>
                                </label>
                            </div>
                            <input type="hidden" name="items[{{ $i }}][total_price]" value="{{ $itemTotal }}" class="receipt-total-input">

                            {{-- 2. Nutrition information --}}
                            @if ($nutrition)
                                <div class="mt-3 rounded-xl bg-white p-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-sm font-semibold">Informasi Gizi</span>

                                        <span class="badge bg-ok-soft text-olive-700">
                                            {{ strtoupper($nutrition['data_source'] ?? 'UNKNOWN') }}
                                        </span>
                                    </div>

                                    <p class="mb-3 text-xs text-muted">
                                        {{ $nutrition['name'] ?? $row['name'] }}
                                        · Per {{ $nutrition['metric_serving_amount'] ?? 100 }}
                                        {{ $nutrition['metric_serving_unit'] ?? 'g' }}
                                    </p>

                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach ([['Kalori', 'calories', 'kkal'], ['Protein', 'protein', 'g'], ['Karbohidrat', 'carbohydrates', 'g'], ['Lemak', 'fat', 'g']] as [$label, $field, $unit])
                                            <div class="rounded-lg bg-sand p-2">
                                                <p class="text-xs text-muted">{{ $label }}</p>
                                                <p class="text-base font-bold">
                                                    {{ $nutrition[$field] ?? '-' }}
                                                    <span class="text-xs font-normal">{{ $unit }}</span>
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="mt-3 rounded-xl bg-white/70 p-3">
                                    <p class="text-xs text-muted">
                                        Informasi gizi belum ditemukan.
                                    </p>
                                </div>
                            @endif
                        @else
                            <p class="mt-1 pl-9 text-[11px] text-muted">
                                Bukan bahan makanan, tidak disimpan.
                            </p>
                        @endif
                    </div>
                @endforeach
                <div
                    class="sticky bottom-0 -mx-5 mt-6 border-t border-line bg-white px-5 pt-3 pb-5 lg:static lg:mx-0 lg:border-0 lg:px-0">
                    <div class="mb-3 flex justify-between text-sm">
                        <span class="text-muted">Total belanja</span>
                        <b id="selected-shopping-total">Rp 0</b>
                    </div>
                    <button id="save-receipt-items" class="btn-primary w-full">Simpan
                        <span id="selected-item-count">{{ collect($rows)->where('is_food', true)->count() }}</span> bahan
                        ke kulkas</button>
                </div>
        </form>
    </div>
    <script>
        (() => {
            const checkboxes = document.querySelectorAll('.receipt-item-checkbox');
            const count = document.querySelector('#selected-item-count');
            const button = document.querySelector('#save-receipt-items');
            const shoppingTotal = document.querySelector('#selected-shopping-total');
            const shelfDays = @json(\App\Services\FoodItemService::SHELF_DAYS);

            const parseNumber = (value) => {
                const digits = String(value ?? '').replace(/[^0-9]/g, '');
                return digits === '' ? 0 : Number(digits);
            };

            const normalizeUnitPrice = (value) => {
                const number = parseNumber(value);
                return number > 0 && number < 1000 ? number * 1000 : number;
            };

            const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 0,
            }).format(value);

            const updateExpiry = (row) => {
                const purchasedInput = row.querySelector('.receipt-purchased-input');
                const expiryInput = row.querySelector('.receipt-expiry-input');
                const categoryInput = row.querySelector('.receipt-category-input');
                if (!purchasedInput?.value || !expiryInput || !categoryInput) return;

                const purchased = new Date(`${purchasedInput.value}T00:00:00`);
                if (Number.isNaN(purchased.getTime())) return;

                purchased.setDate(purchased.getDate() + Number(shelfDays[categoryInput.value] ?? 7));
                const pad = (value) => String(value).padStart(2, '0');
                expiryInput.value = `${purchased.getFullYear()}-${pad(purchased.getMonth() + 1)}-${pad(purchased.getDate())}`;
            };

            const updateRow = (checkbox) => {
                const row = checkbox.closest('[data-receipt-item]');
                if (!row) return;

                const quantity = parseNumber(row.querySelector('.receipt-quantity-input')?.value);
                const unitPrice = normalizeUnitPrice(row.querySelector('.receipt-price-input')?.value);
                const lineTotal = quantity > 0 ? quantity * unitPrice : unitPrice;
                const totalInput = row.querySelector('.receipt-total-input');
                const totalBadge = row.querySelector('[id^="item-total-"]');

                if (totalInput) totalInput.value = lineTotal;
                if (totalBadge) totalBadge.textContent = `Rp ${formatRupiah(lineTotal)}`;

                row.classList.toggle('bg-sand', checkbox.checked);
                row.classList.toggle('bg-sand/50', !checkbox.checked);
                row.classList.toggle('opacity-60', !checkbox.checked);
            };

            const updateCount = () => {
                const selected = [...checkboxes].filter((checkbox) => checkbox.checked && !checkbox.disabled).length;
                if (count) count.textContent = selected;
                if (button) button.disabled = selected === 0;

                let total = 0;
                checkboxes.forEach((checkbox) => {
                    updateRow(checkbox);
                    if (!checkbox.checked || checkbox.disabled) return;
                    const row = checkbox.closest('[data-receipt-item]');
                    total += parseNumber(row?.querySelector('.receipt-total-input')?.value);
                });

                if (shoppingTotal) shoppingTotal.textContent = `Rp ${formatRupiah(total)}`;
            };

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateCount);
                checkbox.closest('[data-receipt-item]')
                    ?.querySelectorAll('.receipt-price-input, .receipt-quantity-input')
                    .forEach((input) => input.addEventListener('input', updateCount));

                const row = checkbox.closest('[data-receipt-item]');
                row?.querySelector('.receipt-category-input')
                    ?.addEventListener('change', () => updateExpiry(row));
                row?.querySelector('.receipt-purchased-input')
                    ?.addEventListener('change', () => updateExpiry(row));
            });
            updateCount();
        })();
    </script>
</x-layouts.app>
