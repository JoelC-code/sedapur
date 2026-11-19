<x-layouts.app title="Daftar belanja" active="shopping.index">
    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

    <x-page-header title="Daftar belanja" subtitle="Dipakai bersama semua anggota rumah">
        <x-avatars />
    </x-page-header>

    <div class="lg:grid lg:grid-cols-[1fr_320px] lg:gap-8">
        <div>
            <form method="POST" action="{{ route('shopping.store') }}" class="space-y-2">
                @csrf
                <div>
                    <label class="sr-only" for="name">Nama barang</label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="100" required class="input" placeholder="Tambah item, mis. bawang merah">
                    @error('name') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="quantity">Jumlah</label>
                        <input id="quantity" name="quantity" type="number" inputmode="decimal" step="any" min="0.01" value="{{ old('quantity', 1) }}" required class="input" placeholder="Jumlah">
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="unit">Satuan</label>
                        <select id="unit" name="unit" class="input">
                            @foreach ($units as $unit)
                                <option @selected(old('unit', 'buah') === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="grid size-[52px] shrink-0 place-items-center rounded-full bg-brand text-white hover:bg-brand-dark" aria-label="Tambah ke daftar belanja"><x-icon name="plus" /></button>
                </div>
                @error('quantity') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                @error('unit') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </form>

            {{-- Realtime memuat ulang isi elemen ini saat anggota lain mengubah daftar (resources/js/shopping-realtime.js). --}}
            <div data-shopping-list
                data-supabase-url="{{ config('services.supabase.url') }}"
                data-supabase-key="{{ config('services.supabase.publishable_key') }}"
                data-household-id="{{ $householdId }}"
                data-token-url="{{ route('session.token') }}"
                data-list-url="{{ route('shopping.list') }}">
                @include('shopping._list')
            </div>
        </div>

        <aside class="mt-6 space-y-3 lg:mt-0">
            <div class="card-sand">
                <p class="flex items-center gap-2 text-sm font-bold"><x-icon name="users" class="size-4 text-olive-700" /> Dipakai bersama</p>
                <p class="mt-1 text-xs text-muted">Semua anggota rumah melihat daftar yang sama.</p>
                <p data-realtime-status data-state="wait" aria-live="polite"
                    class="group mt-2 flex items-center gap-1.5 text-xs font-semibold text-muted data-[state=off]:text-warn data-[state=ok]:text-olive-700">
                    <span class="size-2 shrink-0 rounded-full bg-hint group-data-[state=off]:bg-warn group-data-[state=ok]:bg-olive-700" aria-hidden="true"></span>
                    <span data-realtime-text>Menyambungkan…</span>
                </p>
            </div>
            <div class="rounded-[20px] bg-olive-100 p-4">
                <p class="flex items-center gap-2 text-sm font-bold">Bahan resep otomatis masuk <span class="badge bg-sand-dark text-muted">Segera hadir</span></p>
                <p class="mt-1 text-xs text-muted">Bahan yang belum ada di kulkas dari resep di menu minggu ini ditambahkan ke daftar ini.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
