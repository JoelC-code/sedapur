@php use App\Support\DemoData as D; @endphp
<x-layouts.app title="Beranda" active="dashboard">
    {{-- Header --}}
    <header class="mb-5 flex items-center gap-3 lg:-mx-8 lg:mb-7 lg:h-[76px] lg:gap-4 lg:border-b lg:border-line lg:px-8">
        <span class="grid size-11 place-items-center rounded-full bg-olive-700 text-lg font-extrabold text-white lg:hidden">{{ mb_substr($user['nama'], 0, 1) }}</span>
        <div class="min-w-0 flex-1">
            <button type="button" class="flex items-center gap-1 text-xs text-muted lg:hidden">{{ $household['nama'] }} <x-icon name="chevron-down" class="size-3" /></button>
            <h1 class="text-xl font-extrabold lg:text-[22px]">Halo, {{ $user['panggilan'] }} <span class="hidden lg:inline">👋</span></h1>
            <p class="hidden text-[13px] text-muted lg:block">{{ now()->translatedFormat('l, j F Y') }} · Semua makanan di kulkasmu</p>
        </div>
        <form action="{{ route('fridge.index') }}" class="relative hidden w-80 lg:block">
            <x-icon name="search" class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
            <input name="q" class="input h-11 pl-10" placeholder="Cari bahan atau resep">
        </form>
        <x-avatars class="hidden lg:flex" />
        <a href="{{ route('household.settings') }}" class="grid size-10 place-items-center rounded-full bg-sand lg:hidden" aria-label="Rumah & akun"><x-icon name="user" class="size-4" /></a>
    </header>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            {{-- Statistik --}}
            <div class="grid grid-cols-3 gap-2.5 lg:grid-cols-4 lg:gap-3">
                @foreach ([
                    ['box', $stats['total'], 'Total bahan', ''],
                    ['clock', $stats['segera'], 'Segera kedaluwarsa', 'text-brand'],
                    ['x', $stats['lewat'], 'Sudah kedaluwarsa', 'text-danger', 'hidden lg:block'],
                    ['receipt', $stats['terbuang_short'], 'Terbuang '.$waste['bulan'], ''],
                ] as $s)
                    <div class="min-w-0 rounded-[18px] bg-sand p-3.5 {{ $s[4] ?? '' }}">
                        <x-icon :name="$s[0]" class="size-4 text-muted" />
                        <p class="mt-2 text-[22px] font-extrabold tracking-tight lg:text-2xl {{ $s[3] }}">
                            <span class="lg:hidden">{{ $s[1] }}</span>
                            <span class="hidden lg:inline">{{ $s[0] === 'receipt' ? D::rupiah($waste['terbuang']) : $s[1] }}</span>
                        </p>
                        <p class="stat-label">{{ $s[2] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Perlu perhatian --}}
            <section class="card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="stat-label">Perlu perhatian</p>
                        <h2 class="text-lg font-bold"><span class="text-brand">{{ $expiring->count() }} bahan</span> segera kedaluwarsa</h2>
                    </div>
                    <a href="{{ route('fridge.index') }}" class="text-xs font-semibold text-brand">Lihat semua</a>
                </div>

                {{-- HP: kartu --}}
                <div class="mt-3 space-y-2 lg:hidden">
                    @foreach ($expiring->take(3) as $item)
                        <x-food-row :item="$item" />
                    @endforeach
                </div>

                {{-- Desktop: tabel --}}
                <table class="mt-4 hidden w-full text-sm lg:table">
                    <thead>
                        <tr class="text-left text-[10px] tracking-wide text-muted uppercase">
                            <th class="pb-2 font-semibold">Bahan</th><th class="pb-2 font-semibold">Jumlah</th><th class="pb-2 font-semibold">Ditambah</th><th class="pb-2 font-semibold">Kedaluwarsa</th><th class="pb-2 font-semibold">Aksi cepat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand">
                        @foreach ($expiring as $item)
                            @php [$label, $cls] = D::expiryBadge($item['sisa_hari']); @endphp
                            <tr>
                                <td class="py-2.5"><a href="{{ route('fridge.show', $item['id']) }}" class="flex items-center gap-2 font-semibold"><span class="text-lg">{{ $item['emoji'] }}</span>{{ $item['nama'] }}</a></td>
                                <td class="py-2.5">{{ $item['jumlah'] }} {{ $item['satuan'] }}</td>
                                <td class="py-2.5">{{ $item['ditambah_oleh'] }}</td>
                                <td class="py-2.5"><span class="badge {{ $cls }}">{{ $label }}</span></td>
                                <td class="py-2.5"><a href="{{ route('recipes.index') }}" class="chip">Cari resep</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Menu hari ini --}}
            <section>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-bold">Menu hari ini</h2>
                    <a href="{{ route('menu.index') }}" class="text-xs font-semibold text-brand">Mingguan</a>
                </div>
                <div class="grid grid-cols-1 gap-2 lg:grid-cols-3">
                    @foreach ($todayMenu as $waktu => $meal)
                        <a href="{{ $meal['recipe_id'] ? route('recipes.show', $meal['recipe_id']) : route('menu.index') }}" class="flex items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
                            <span class="grid size-10 place-items-center rounded-xl bg-white text-xl">{{ $meal['emoji'] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="stat-label">{{ $waktu }} · {{ $meal['kalori'] }} kkal</span>
                                <span class="block truncate text-sm font-semibold">{{ $meal['nama'] }}</span>
                            </span>
                            <x-icon name="chevron-right" class="size-4 text-muted lg:hidden" />
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Lainnya (HP) --}}
            <section class="lg:hidden">
                <h2 class="mb-2 font-bold">Lainnya</h2>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([
                        ['nutrition.index', 'flame', 'Gizi & kalori', D::number($nutrition['konsumsi']['hari_ini']).' kkal hari ini', 'text-brand'],
                        ['waste.index', 'chart', 'Food waste', D::rupiah($waste['terbuang']).' bulan ini', 'text-danger'],
                        ['activity.index', 'history', 'Riwayat', '12 aktivitas hari ini', 'text-olive-700'],
                        ['household.settings', 'users', 'Rumah', count($household['members']).' anggota', 'text-info'],
                    ] as [$route, $icon, $label, $desc, $color])
                        <a href="{{ route($route) }}" class="card-sand hover:bg-sand-dark">
                            <span class="grid size-8 place-items-center rounded-lg bg-white {{ $color }}"><x-icon :name="$icon" class="size-4" /></span>
                            <p class="mt-3 text-sm font-semibold">{{ $label }}</p>
                            <p class="text-[11px] text-muted">{{ $desc }}</p>
                        </a>
                    @endforeach
                </div>
            </section>

            <div class="space-y-3 lg:hidden">
                @include('partials.ai-summary-card')
                <div class="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-black/10">
                    <x-icon name="phone" class="size-5 text-olive-700" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Pasang {{ config('app.name') }} di HP</p>
                        <p class="text-[11px] text-muted">Buka seperti app, langsung dari home screen.</p>
                    </div>
                    <button type="button" id="install-pwa" class="btn-primary px-4 py-2 text-xs">Pasang</button>
                </div>
            </div>
        </div>

        {{-- Kolom kanan (desktop) --}}
        <aside class="hidden space-y-5 lg:block">
            @include('partials.ai-summary-card')

            <section class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Aktivitas terbaru</h2>
                    <a href="{{ route('activity.index') }}" class="text-xs font-semibold text-brand">Semua</a>
                </div>
                <ul class="space-y-3">
                    @foreach (collect($activities)->flatMap(fn ($g) => $g['items'])->take(4) as [$who, $what, $note, $aksi, $time])
                        <li class="flex items-center gap-2 text-xs">
                            @include('partials.member-dot', ['name' => $who])
                            <span class="flex-1"><b>{{ $who }}</b> {{ $what }}</span>
                            <span class="text-muted">{{ $time }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Food waste 6 bulan</h2>
                    <span class="text-[10px] text-muted">Rp ribu</span>
                </div>
                @include('partials.bar-chart', ['bars' => $waste['riwayat'], 'height' => 'h-28'])
            </section>
        </aside>
    </div>

    <script>
        // Tombol "Pasang" memakai prompt instalasi PWA bila browser mendukung.
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; });
        document.getElementById('install-pwa')?.addEventListener('click', () => deferredPrompt?.prompt());
    </script>
</x-layouts.app>
