@php
    $editing = $item !== null;
    $today = now('Asia/Jakarta')->toDateString();
    $category = old('category', $item['kategori'] ?? 'lainnya');
@endphp
<x-layouts.app :title="$editing ? 'Ubah bahan' : 'Input manual'" active="fridge.index" bare>
    <x-page-header :title="$editing ? 'Ubah bahan' : 'Input manual'" :back="$editing ? route('fridge.show', $item['id']) : route('fridge.index')" />

    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

    <form method="POST" action="{{ $editing ? route('fridge.update', $item['id']) : route('fridge.store') }}" class="space-y-4 lg:max-w-2xl" @unless ($editing) data-shelf-life @endunless>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label class="label" for="name">Nama bahan</label>
            <input id="name" name="name" class="input" value="{{ old('name', $item['nama'] ?? '') }}" placeholder="Contoh: Susu UHT" maxlength="120" required autocomplete="off">
            @error('name') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="label">Kategori</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $key => $cat)
                    <label class="chip cursor-pointer has-checked:bg-ink has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-olive-200">
                        <input type="radio" name="category" value="{{ $key }}" class="sr-only" data-shelf-days="{{ $shelfDays[$key] }}" @checked($category === $key)>
                        {{ $cat['label'] }}
                    </label>
                @endforeach
            </div>
            @error('category') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        </fieldset>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="quantity">Jumlah</label>
                <input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal" class="input" value="{{ old('quantity', $item['jumlah_angka'] ?? 1) }}" required>
                @error('quantity') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="unit">Satuan</label>
                <select id="unit" name="unit" class="input">
                    @foreach ($units as $unit)
                        <option @selected(old('unit', $item['satuan'] ?? 'buah') === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
                @error('unit') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="weight_grams">Berat (g) · opsional</label>
                <input id="weight_grams" name="weight_grams" type="number" min="1" step="any" inputmode="decimal" class="input" value="{{ old('weight_grams', $item['berat_gram'] ?? '') }}">
                @error('weight_grams') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="price">Harga (Rp) · opsional</label>
                <input id="price" name="price" type="number" min="0" step="1" inputmode="numeric" class="input" value="{{ old('price', $item['harga'] ?? '') }}" placeholder="28000">
                @error('price') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="purchased_at">Tgl beli</label>
                <input id="purchased_at" name="purchased_at" type="date" class="input" value="{{ old('purchased_at', $editing ? $item['tgl_beli']?->toDateString() : $today) }}">
                @error('purchased_at') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="expires_at">Kedaluwarsa</label>
                <input id="expires_at" name="expires_at" type="date" class="input" value="{{ old('expires_at', $editing ? $item['tgl_kedaluwarsa']?->toDateString() : now('Asia/Jakarta')->addDays($shelfDays[$category])->toDateString()) }}" required>
                @error('expires_at') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="text-xs text-muted" data-shelf-hint>
            @if ($editing)
                Jumlah awal tidak ikut berubah; dipakai untuk menghitung sisa dan uang terbuang.
            @else
                Tanggal kedaluwarsa diisi perkiraan sesuai kategori ({{ $categories[$category]['label'] }} ±{{ $shelfDays[$category] }} hari). Ubah bila kemasan menulis tanggal lain.
            @endif
        </p>

        <button class="btn-primary w-full lg:w-auto">{{ $editing ? 'Simpan perubahan' : 'Tambah ke kulkas' }}</button>
    </form>
</x-layouts.app>
