@php
    $dayIndex = (int) request('hari', collect($plan['days'])->search(fn ($d) => $d['is_today']) ?: 0);
    $day = $plan['days'][$dayIndex] ?? $plan['days'][0];
    $range = $plan['mulai']->translatedFormat('j M').' – '.$plan['selesai']->translatedFormat('j M Y');
@endphp
<x-layouts.app title="Menu mingguan" active="menu.index">
    <x-page-header title="Menu & resep" subtitle="Menu dibuat Gemini dari isi kulkas, mendahulukan bahan yang paling cepat kedaluwarsa" class="hidden lg:flex">
        <div class="seg w-72">
            <a href="{{ route('menu.index') }}" class="is-active">Menu mingguan</a>
            <a href="{{ route('recipes.index') }}">Resep</a>
        </div>
    </x-page-header>

    <div class="seg mb-4 lg:hidden">
        <a href="{{ route('menu.index') }}" class="is-active">Menu mingguan</a>
        <a href="{{ route('recipes.index') }}">Resep</a>
    </div>

    <div class="flex items-center gap-3">
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <button class="hidden size-8 place-items-center rounded-full bg-sand lg:grid" aria-label="Minggu sebelumnya"><x-icon name="chevron-left" class="size-4" /></button>
                <h2 class="text-xl font-extrabold lg:text-2xl">{{ $range }}</h2>
                <button class="hidden size-8 place-items-center rounded-full bg-sand lg:grid" aria-label="Minggu berikutnya"><x-icon name="chevron-right" class="size-4" /></button>
            </div>
            <p class="mt-1 flex items-center gap-2 text-xs text-muted"><span class="badge bg-ai-soft text-ai">✦ AI</span> Dibuat Senin pagi · 1.770 kkal/hari</p>
        </div>
        <p class="hidden items-center gap-2 rounded-full bg-olive-100 px-3 py-2 text-xs lg:flex"><x-icon name="clock" class="size-3.5 text-olive-700" /> Mendahulukan 3 bahan hampir kedaluwarsa</p>
        <button class="btn-soft btn-sm hidden lg:inline-flex"><x-icon name="sparkles" class="size-4" /> Buat ulang dengan AI</button>
        <button class="grid size-10 place-items-center rounded-full bg-sand lg:hidden" aria-label="Buat ulang"><x-icon name="refresh" class="size-4" /></button>
    </div>

    {{-- HP: strip hari + kartu menu --}}
    <div class="lg:hidden">
        <div class="mt-4 flex gap-1.5">
            @foreach ($plan['days'] as $i => $d)
                <a href="{{ route('menu.index', ['hari' => $i]) }}"
                   @class(['flex flex-1 flex-col items-center gap-0.5 rounded-[14px] py-2 text-[11px] font-semibold', 'bg-ink text-white/70' => $i === $dayIndex, 'bg-sand text-muted' => $i !== $dayIndex])>
                    {{ $d['date']->translatedFormat('D') }}
                    <b @class(['text-base', 'text-white' => $i === $dayIndex, 'text-ink' => $i !== $dayIndex])>{{ $d['date']->format('j') }}</b>
                </a>
            @endforeach
        </div>

        <p class="mt-4 flex items-center gap-2 rounded-2xl bg-olive-100 px-3 py-2.5 text-xs"><x-icon name="clock" class="size-3.5 text-olive-700" /> Mendahulukan 3 bahan yang hampir kedaluwarsa</p>

        <div class="mt-3 space-y-2.5">
            @foreach ($day['meals'] as $waktu => $meal)
                <a href="{{ $meal['recipe_id'] ? route('recipes.show', $meal['recipe_id']) : route('recipes.index') }}" class="flex items-center gap-3 rounded-[18px] bg-sand p-3.5">
                    <span class="min-w-0 flex-1">
                        <span class="stat-label">{{ $waktu }} · {{ $meal['kalori'] }} kkal</span>
                        <span class="block truncate text-[15px] font-bold">{{ $meal['nama'] }}</span>
                        @if ($meal['recipe_id'] === 1)
                            <span class="mt-1.5 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                <span class="rounded-full bg-warn-soft px-2 py-0.5 text-warn">Bayam</span>
                                <span class="rounded-full bg-warn-soft px-2 py-0.5 text-warn">Tomat</span>
                                <span class="rounded-full bg-white px-2 py-0.5">Telur</span>
                            </span>
                        @endif
                    </span>
                    <x-icon name="chevron-right" class="size-4 text-muted" />
                </a>
            @endforeach
        </div>

        <button class="btn-soft mt-4 w-full"><x-icon name="sparkles" class="size-4" /> Buat ulang menu dengan AI</button>
    </div>

    {{-- Desktop: kalender 7 hari × pagi/siang/malam --}}
    <div class="mt-6 hidden grid-cols-[72px_repeat(7,minmax(0,1fr))] gap-2 lg:grid">
        <span></span>
        @foreach ($plan['days'] as $d)
            <div @class(['rounded-xl py-2 text-center text-xs font-semibold', 'bg-ink text-white/70' => $d['is_today'], 'bg-sand text-muted' => ! $d['is_today']])>
                {{ $d['date']->translatedFormat('D') }}
                <b @class(['block text-lg', 'text-white' => $d['is_today'], 'text-ink' => ! $d['is_today']])>{{ $d['date']->format('j') }}</b>
            </div>
        @endforeach

        @foreach (['pagi', 'siang', 'malam'] as $waktu)
            <span class="stat-label flex items-center">{{ $waktu }}</span>
            @foreach ($plan['days'] as $d)
                @php $meal = $d['meals'][$waktu]; @endphp
                <a href="{{ $meal['recipe_id'] ? route('recipes.show', $meal['recipe_id']) : route('recipes.index') }}"
                   @class(['flex min-h-[132px] flex-col gap-1 rounded-[14px] p-3 transition hover:brightness-95', 'bg-olive-100 shadow-[inset_0_0_0_2px_var(--color-olive-200)]' => $d['is_today'], 'bg-sand' => ! $d['is_today']])>
                    <b class="text-[13px] leading-tight">{{ $meal['nama'] }}</b>
                    <span class="text-[11px] text-muted">{{ $meal['kalori'] }} kkal</span>
                    @if ($meal['recipe_id'] === 1)
                        <span class="mt-auto flex gap-1 text-[10px] font-semibold text-warn"><span>Bayam</span><span>Tomat</span></span>
                    @endif
                </a>
            @endforeach
        @endforeach
    </div>
</x-layouts.app>
