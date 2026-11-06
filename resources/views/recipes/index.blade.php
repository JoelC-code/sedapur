<x-layouts.app title="Daftar resep" active="menu.index">
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

    <form class="relative lg:max-w-md">
        <x-icon name="search" class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
        <input name="q" class="input pl-10" placeholder="Cari resep">
    </form>

    <h2 class="mt-5 mb-2.5 font-bold">Dari isi kulkasmu</h2>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($fromFridge as $r)
            <a href="{{ route('recipes.show', $r['id']) }}" class="overflow-hidden rounded-[18px] bg-sand hover:bg-sand-dark">
                <div class="grid h-24 place-items-center text-5xl {{ $r['bg'] }}">{{ $r['emoji'] }}</div>
                <div class="p-3">
                    <p class="text-sm leading-tight font-bold">{{ $r['nama'] }}</p>
                    <p class="mt-1 text-[11px] text-muted">{{ $r['kalori'] }} kkal · {{ $r['porsi'] }} porsi</p>
                    <p class="mt-1 text-[11px] font-semibold text-warn">Pakai {{ collect($r['bahan'])->whereNotNull('tag')->pluck('nama')->map(fn ($n) => mb_strtolower($n))->join(' & ') }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <h2 class="mt-6 mb-2.5 font-bold">Resep tersimpan</h2>
    <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
        @foreach ($saved as $r)
            <a href="{{ route('recipes.show', $r['id']) }}" class="flex items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
                <span class="emo">{{ $r['emoji'] }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[15px] font-bold">{{ $r['nama'] }}</span>
                    <span class="text-xs text-muted">{{ $r['porsi'] }} porsi · {{ $r['kalori'] }} kkal/porsi</span>
                </span>
                <span class="badge bg-white text-muted">{{ $r['menit'] }} mnt</span>
            </a>
        @endforeach
    </div>

    <button class="btn-primary fixed right-5 bottom-28 z-20 shadow-lg lg:static lg:mt-6"><x-icon name="sparkles" class="size-4" /> Buat resep</button>
</x-layouts.app>
