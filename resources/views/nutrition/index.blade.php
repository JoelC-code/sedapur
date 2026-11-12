@php
    use App\Support\DemoData as D;
    $k = $n['konsumsi'];
    $f = $n['kulkas'];
    $macroTotal = $k['protein'] + $k['karbo'] + $k['lemak'];
@endphp
<x-layouts.app title="Gizi & kalori" active="nutrition.index">
    <x-page-header title="Gizi & kalori" subtitle="Dihitung dari porsi yang dicatat dan data gizi per bahan" :back="route('dashboard')">
        <span class="chip hidden lg:inline-flex">Minggu ini ▾</span>
    </x-page-header>

    <div class="seg mb-4 lg:hidden">
        <a href="{{ route('nutrition.index', ['tab' => 'saya']) }}" @class(['is-active' => $tab === 'saya'])>Konsumsi saya</a>
        <a href="{{ route('nutrition.index', ['tab' => 'kulkas']) }}" @class(['is-active' => $tab === 'kulkas'])>Isi kulkas</a>
    </div>

    <div class="lg:grid lg:grid-cols-2 lg:gap-6">
        {{-- Konsumsi saya --}}
        <div @class(['space-y-4', 'hidden lg:block' => $tab !== 'saya'])>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1.2fr]">
                <section class="card-sand">
                    <p class="stat-label">Konsumsi saya · hari ini</p>
                    <p class="mt-1 text-3xl font-extrabold">{{ D::number($k['hari_ini']) }} <span class="text-sm font-semibold text-muted">/ {{ D::number($k['target']) }} kkal</span></p>
                    <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-sand-dark">
                        <i class="bg-olive-700" style="width: {{ $k['protein'] / $macroTotal * 62 }}%"></i>
                        <i class="bg-amber" style="width: {{ $k['karbo'] / $macroTotal * 62 }}%"></i>
                        <i class="bg-brand" style="width: {{ $k['lemak'] / $macroTotal * 62 }}%"></i>
                    </div>
                    <ul class="mt-3 space-y-1 text-xs">
                        <li class="flex items-center gap-2"><i class="size-2 rounded-full bg-olive-700"></i> Protein {{ $k['protein'] }} g</li>
                        <li class="flex items-center gap-2"><i class="size-2 rounded-full bg-amber"></i> Karbo {{ $k['karbo'] }} g</li>
                        <li class="flex items-center gap-2"><i class="size-2 rounded-full bg-brand"></i> Lemak {{ $k['lemak'] }} g</li>
                    </ul>
                </section>

                <section class="card">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-bold">Minggu ini</h2>
                        <span class="text-[11px] text-muted">rata-rata 1.700 kkal</span>
                    </div>
                    @include('partials.bar-chart', ['bars' => $k['minggu'], 'highlight' => 3, 'height' => 'h-28'])
                </section>
            </div>

            <section class="card">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-bold">Tercatat hari ini</h2>
                    <a href="{{ route('recipes.index') }}" class="text-xs font-semibold text-brand">+ Catat porsi</a>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($k['log'] as [$nama, , $porsi, $jam, $kal, $pro, $karbo, $lemak])
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ $nama }}</span>
                                <span class="text-[11px] text-muted">{{ $porsi }} porsi · {{ $jam }} · P {{ $pro }} g · K {{ $karbo }} g · L {{ $lemak }} g</span>
                            </span>
                            <b class="text-sm">{{ $kal }}</b>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- Isi kulkas --}}
        <div @class(['space-y-4', 'hidden lg:block' => $tab !== 'kulkas'])>
            <section class="card-sand">
                <p class="stat-label">Total isi kulkas · {{ $f['jumlah_bahan'] }} bahan</p>
                <p class="mt-1 text-3xl font-extrabold">{{ D::number($f['total']) }} <span class="text-sm font-semibold text-muted">kkal</span></p>
                <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                    @foreach ([[$f['protein'], 'protein'], [$f['karbo'], 'karbo'], [$f['lemak'], 'lemak'], [$f['berat'], 'berat']] as [$v, $l])
                        <div class="rounded-[14px] bg-white px-1 py-2"><b class="block text-sm font-extrabold">{{ $v }}</b><span class="text-[11px] text-muted">{{ $l }}</span></div>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <h2 class="font-bold">Sumber data gizi</h2>
                @php $totalSrc = array_sum($f['sumber']); @endphp
                <div class="mt-3 flex h-2.5 overflow-hidden rounded-full">
                    <i class="bg-info" style="width: {{ $f['sumber']['fatsecret'] / $totalSrc * 100 }}%"></i>
                    <i class="bg-olive-700" style="width: {{ $f['sumber']['tkpi'] / $totalSrc * 100 }}%"></i>
                    <i class="bg-ai" style="width: {{ $f['sumber']['estimasi_ai'] / $totalSrc * 100 }}%"></i>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                    @foreach ($f['sumber'] as $src => $count)
                        @php [$l, $c] = D::sourceBadge($src); @endphp
                        <span class="badge {{ $c }}">{{ $l }} · {{ $count }}</span>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <h2 class="mb-1 font-bold">Penyumbang kalori terbesar</h2>
                <ul class="divide-y divide-line">
                    @foreach ($f['penyumbang'] as [$nama, , $berat, $kal])
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <span class="flex-1"><b>{{ $nama }}</b> <span class="text-xs text-muted">· {{ $berat }}</span></span>
                            <b>{{ D::number($kal) }} kkal</b>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-[11px] text-muted">Dihitung dari berat bahan × nilai gizi per 100 g.</p>
            </section>
        </div>
    </div>
</x-layouts.app>
