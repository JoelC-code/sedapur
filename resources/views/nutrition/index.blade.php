@php
    use App\Support\DemoData as D;
    $k = $n['konsumsi'];
    $f = $n['kulkas'];
    $macroTotal = max(1, $k['protein'] + $k['karbo'] + $k['lemak']);
@endphp
<x-layouts.app title="Gizi & kalori" active="nutrition.index">
    <x-page-header title="Gizi & kalori" subtitle="Dihitung dari porsi yang dicatat dan data gizi per bahan" :back="route('dashboard')">
        <span class="chip hidden lg:inline-flex">Minggu ini</span>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

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
                        <span class="text-[11px] text-muted">rata-rata {{ D::number($k['rata_rata']) }} kkal</span>
                    </div>
                    @include('partials.bar-chart', ['bars' => $k['minggu'], 'height' => 'h-28'])
                </section>
            </div>

            <section class="card">
                <h2 class="mb-2 font-bold">Tercatat hari ini</h2>

                {{-- Cukup nama + berat: kalori dan makro dihitung server dari FatSecret/TKPI per 100 g. --}}
                <form method="POST" action="{{ route('consumption.store') }}" class="mb-2 space-y-2">
                    @csrf
                    <div class="flex gap-2">
                        <div class="min-w-0 flex-[2]">
                            <label class="sr-only" for="consumption-name">Nama makanan</label>
                            <input id="consumption-name" name="name" value="{{ old('name') }}" maxlength="120" required class="input" placeholder="Catat makan, mis. nasi putih">
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="sr-only" for="consumption-grams">Berat (gram)</label>
                            <input id="consumption-grams" name="grams" type="number" inputmode="decimal" step="any" min="1" max="5000" value="{{ old('grams') }}" required class="input" placeholder="gram">
                        </div>
                        <button class="grid size-[52px] shrink-0 place-items-center rounded-full bg-brand text-white hover:bg-brand-dark" aria-label="Catat porsi"><x-icon name="plus" /></button>
                    </div>
                    @error('name') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                    @error('grams') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                </form>

                <ul class="divide-y divide-line">
                    @forelse ($k['log'] as $log)
                        @php [$srcLabel, $srcClass] = D::sourceBadge($log['sumber']); @endphp
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2"><span class="truncate text-sm font-bold">{{ $log['nama'] }}</span> <span class="badge {{ $srcClass }}">{{ $srcLabel }}</span></span>
                                <span class="text-[11px] text-muted">{{ $log['gram'] }} g · {{ $log['jam'] }} · P {{ D::number($log['protein']) }} g · K {{ D::number($log['karbo']) }} g · L {{ D::number($log['lemak']) }} g</span>
                            </span>
                            <b class="text-sm">{{ D::number($log['kalori']) }}</b>
                            <form method="POST" action="{{ route('consumption.destroy', $log['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-full p-1 text-muted hover:bg-sand hover:text-danger" aria-label="Hapus catatan {{ $log['nama'] }}"><x-icon name="x" class="size-4" /></button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2.5 text-sm text-muted">Belum ada yang dicatat hari ini.</li>
                    @endforelse
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
