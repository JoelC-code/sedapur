@php use App\Support\DemoData as D; @endphp
<x-layouts.app title="Analitik food waste" active="waste.index">
    <x-page-header title="Food waste" subtitle="Bahan yang dibuang × harga beli di struk" :back="route('dashboard')">
        <span class="chip">{{ $w['bulan'] }} {{ now()->year }} ▾</span>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <section class="rounded-[20px] bg-olive-700 p-4 text-white">
                    <p class="text-[10px] font-bold tracking-[.07em] text-white/70 uppercase">Uang terbuang bulan ini</p>
                    <p class="mt-1 text-3xl font-extrabold">{{ D::rupiah($w['terbuang']) }}</p>
                    <p class="mt-2 flex items-center gap-2 text-xs text-white/80">
                        <span class="badge bg-olive-200 text-olive-700">{{ $w['perubahan'] }}%</span> dibanding September ({{ D::rupiah($w['bulan_lalu']) }})
                    </p>
                </section>
                <div class="grid grid-cols-3 gap-3 lg:contents">
                    <div class="card-sand"><p class="text-2xl font-extrabold">{{ $w['dimasak'] }}</p><p class="stat-label">Bahan dimasak</p></div>
                    <div class="card-sand"><p class="text-2xl font-extrabold text-danger">{{ $w['dibuang'] }}</p><p class="stat-label">Bahan dibuang</p></div>
                    <div class="card-sand"><p class="text-2xl font-extrabold text-olive-700">{{ $w['terpakai'] }}%</p><p class="stat-label">Bahan terpakai</p></div>
                </div>
            </div>

            <section class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">6 bulan terakhir</h2>
                    <span class="text-[11px] text-muted">Rp ribu</span>
                </div>
                @include('partials.bar-chart', ['bars' => array_map(fn ($r) => [$r[0], $r[1]], $w['riwayat']), 'height' => 'h-36 lg:h-64'])
            </section>
        </div>

        <div class="space-y-4">
            <section class="card">
                <h2 class="mb-1 font-bold">Paling banyak terbuang</h2>
                <ul class="divide-y divide-line">
                    @foreach ($w['terbanyak'] as [$nama, $emoji, $kali, $rp])
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <span class="text-xl">{{ $emoji }}</span>
                            <span class="flex-1"><b>{{ $nama }}</b> <span class="text-xs text-muted">· {{ $kali }}×</span></span>
                            <b>{{ D::rupiah($rp) }}</b>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-[20px] bg-olive-100 p-4">
                <p class="flex items-center gap-2 text-sm font-bold"><x-icon name="sparkles" class="size-4 text-ai" /> Ringkasan AI · {{ $w['bulan'] }}</p>
                <p class="mt-2 text-sm leading-relaxed">{{ $w['ringkasan'] }}</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm">
                    @foreach ($w['saran'] as $s)
                        <li>{{ $s }}</li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
