<x-layouts.app title="Daftar resep" active="menu.index">
    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

    <x-page-header title="Menu & resep" subtitle="Resep dari isi kulkas dan resep yang kamu simpan" class="hidden lg:flex">
        <div class="seg w-72">
            <a href="{{ route('menu.index') }}">Menu mingguan</a>
            <a href="{{ route('recipes.index') }}" class="is-active">Resep</a>
        </div>
    </x-page-header>

    <div class="seg mb-4 lg:hidden">
        <a href="{{ route('menu.index') }}">Menu mingguan</a>
        <a href="{{ route('recipes.index') }}" class="is-active">Resep</a>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form class="relative flex-1 lg:max-w-md">
            <x-icon name="search" class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $search }}" class="input pl-10" placeholder="Cari resep">
        </form>

        <form method="POST" action="{{ route('recipes.generate') }}">
            @csrf
            <button class="btn-primary w-full sm:w-auto" data-loading-label="Membuat resep…">
                <x-icon name="sparkles" class="size-4" /> Buat resep dengan AI
            </button>
        </form>
    </div>

    @if ($loadFailed)
        <div class="card-sand mt-5 py-10 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-full bg-white text-olive-700"><x-icon name="book" class="size-5" /></span>
            <p class="mt-2 font-bold">Daftar resep belum bisa dimuat</p>
            <p class="text-sm text-muted">Koneksi ke server sedang bermasalah. Muat ulang halaman sebentar lagi.</p>
        </div>
    @else
        <h2 class="mt-5 mb-2.5 font-bold">Dari isi kulkasmu</h2>
        @if ($fromFridge->isEmpty())
            <div class="card-sand py-8 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-white text-olive-700"><x-icon name="sparkles" class="size-5" /></span>
                <p class="mt-2 font-bold">{{ $search !== '' ? 'Resep tidak ditemukan' : 'Belum ada resep dari kulkasmu' }}</p>
                <p class="mt-1 text-sm text-muted">
                    @if ($search !== '')
                        Coba kata kunci lain atau hapus pencarian.
                    @elseif ($fridgeCount === 0)
                        Tambahkan bahan ke kulkas dulu, lalu minta AI membuatkan rekomendasi resep.
                    @else
                        Tekan "Buat resep dengan AI" untuk membuat 3 resep dari {{ $fridgeCount }} bahan di kulkasmu.
                    @endif
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($fromFridge as $r)
                    @php
                        $urgent = collect($r['bahan'])->whereNotNull('tag')->pluck('nama')->map(fn ($n) => mb_strtolower($n))->unique()->take(3);
                        $availableCount = collect($r['bahan'])->where('ada', true)->count();
                    @endphp
                    <a href="{{ route('recipes.show', $r['id']) }}" class="flex flex-col overflow-hidden rounded-[18px] bg-sand p-3.5 hover:bg-sand-dark">
                        <div class="flex items-center justify-between gap-2">
                            @if ($r['dibuat_ai'])
                                <span class="badge bg-ai-soft text-ai">✦ AI</span>
                            @endif
                            <span class="text-[11px] text-muted">{{ $r['menit'] }} mnt</span>
                        </div>
                        <p class="mt-2 text-sm leading-tight font-bold">{{ $r['nama'] }}</p>
                        <p class="mt-1 text-[11px] text-muted">{{ $r['kalori'] }} kkal · {{ $r['porsi'] }} porsi · {{ $availableCount }}/{{ count($r['bahan']) }} bahan ada</p>
                        @if ($urgent->isNotEmpty())
                            <p class="mt-auto pt-2 text-[11px] font-semibold text-warn">Prioritas: {{ $urgent->join(' & ') }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        <h2 class="mt-6 mb-2.5 font-bold">Resep tersimpan</h2>
        @if ($saved->isEmpty())
            <p class="card text-sm text-muted">Belum ada resep yang disimpan. Buka detail resep lalu tekan "Simpan" untuk menyimpannya di sini.</p>
        @else
            <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                @foreach ($saved as $r)
                    <a href="{{ route('recipes.show', $r['id']) }}" class="flex items-center gap-3 rounded-2xl bg-sand p-3.5 hover:bg-sand-dark">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[15px] font-bold">{{ $r['nama'] }}</span>
                            <span class="text-xs text-muted">{{ $r['porsi'] }} porsi · {{ $r['kalori'] }} kkal/porsi</span>
                        </span>
                        <span class="badge bg-white text-muted">{{ $r['menit'] }} mnt</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.app>
