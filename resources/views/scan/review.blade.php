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
                            <span>Rp {{ number_format($row['total_price'] ?? 0, 0, ',', '.') }}</span>
                        </p>
                    @endforeach
                    <hr class="my-1.5 border-dashed border-black/30">
                    <p class="flex justify-between font-bold">
                        <span>TOTAL</span><span>Rp {{ number_format($result['total'] ?? 0, 0, ',', '.') }}</span></p>
                </div>
                <p class="mt-6 text-xs text-muted">Foto tersimpan di bucket privat</p>
            </div>
        </div>

        <form method="GET" action="{{ route('fridge.index') }}">
            <p class="flex gap-2 rounded-2xl bg-olive-100 p-3 text-xs">
                <x-icon name="sparkles" class="size-4 shrink-0 text-ai" />
                Ubah yang salah sebelum disimpan. Tanggal kedaluwarsa dari AI adalah perkiraan.
            </p>

            <div class="mt-3 space-y-2.5">
                @foreach ($rows as $i => $row)
                    @php
                        $isFood = $row['is_food'] ?? false;
                        $nutrition = $row['nutrition']['data'] ?? null;
                        $itemPrice = (float) ($row['total_price'] ?? 0);
                    @endphp
                    <div @class([
                        'rounded-2xl p-2.5',
                        'bg-sand' => $isFood,
                        'bg-sand/50 opacity-60' => !$isFood,
                    ])>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="items[{{ $i }}][selected]" value="1"
                                @checked($isFood)
                                class="size-4 rounded border-2 border-sand-dark text-olive-700 focus:ring-olive-700">
                            <input type="text" name="items[{{ $i }}][name]"
                                value="{{ $row['name'] ?? 'Noname' }}"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] font-bold focus:ring-0"
                                aria-label="Nama bahan">
                            <span class="shrink-0 rounded-full bg-white px-2 py-1 text-[11px] font-bold text-olive-700">
                                Rp {{ number_format($itemPrice, 0, ',', '.') }}
                            </span>
                            <x-icon name="edit" class="size-4 text-muted" />
                        </div>

                        @if ($isFood)
                            {{-- 1. Quantity, price and unit --}}
                            <div class="mt-2.5 grid grid-cols-3 gap-2">
                                @foreach ([['Jumlah', 'quantity', $row['quantity'] ?? 1], ['Harga (Rp)', 'total_price', $row['total_price'] ?? 0], ['Satuan', 'unit', $row['unit'] ?? '']] as [$label, $field, $value])
                                    <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                        <small class="block text-[10px] font-semibold text-muted">
                                            {{ $label }}
                                        </small>
                                        <input name="items[{{ $i }}][{{ $field }}]"
                                            value="{{ $value }}"
                                            class="w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                    </label>
                                @endforeach
                            </div>

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
                    <div class="mb-3 flex justify-between text-sm">                    <span class="text-muted">Total
                        belanja</span><b>Rp {{ number_format($result['total'] ?? 0, 0, ',', '.') }}</b></div>
                    <button class="btn-primary w-full">Simpan
                        {{ collect($rows)->where('is_food', true)->count() }} bahan
                        ke kulkas</button>
                </div>
        </form>
    </div>
</x-layouts.app>
