<x-layouts.app title="Daftar belanja" active="shopping.index">
    <x-page-header title="Daftar belanja" subtitle="Tersinkron ke 3 anggota">
        <x-avatars />
    </x-page-header>

    <div class="lg:grid lg:grid-cols-[1fr_320px] lg:gap-8">
        <div>
            <form class="flex gap-2" onsubmit="event.preventDefault()">
                <input class="input" placeholder="Tambah item…" aria-label="Tambah item">
                <button class="grid size-[52px] shrink-0 place-items-center rounded-full bg-brand text-white" aria-label="Tambah"><x-icon name="plus" /></button>
            </form>

            @foreach ($groups as $g)
                <p class="stat-label mt-5 mb-2">{{ $g['grup'] }}</p>
                <ul class="space-y-2">
                    @foreach ($g['items'] as [$nama, $jumlah, $checked])
                        <li @class(['flex items-center gap-3 rounded-2xl bg-sand px-3.5 py-3 transition', 'opacity-50' => $checked])>
                            <input type="checkbox" data-shopping @checked($checked) class="size-6 rounded-lg accent-olive-700" aria-label="Sudah dibeli: {{ $nama }}">
                            <span data-name @class(['flex-1 text-[15px] font-semibold', 'line-through' => $checked])>{{ $nama }}</span>
                            <span class="text-xs text-muted">{{ $jumlah }}</span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>

        <aside class="mt-6 space-y-3 lg:mt-0">
            <div class="card-sand">
                <p class="flex items-center gap-2 text-sm font-bold"><x-icon name="refresh" class="size-4 text-olive-700" /> Realtime</p>
                <p class="mt-1 text-xs text-muted">Centang di HP mana pun langsung terlihat oleh semua anggota rumah.</p>
            </div>
            <div class="rounded-[20px] bg-olive-100 p-4">
                <p class="text-sm font-bold">Bahan resep otomatis masuk</p>
                <p class="mt-1 text-xs text-muted">Bahan yang belum ada di kulkas dari resep di menu minggu ini ditambahkan ke daftar ini.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
