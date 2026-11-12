@php $missing = collect($recipe['bahan'])->where('ada', false); @endphp
<x-layouts.app :title="$recipe['nama']" active="menu.index" bare>
    <x-page-header :title="$recipe['nama']" subtitle="Menu & resep · {{ $recipe['dibuat_ai'] ? 'Dibuat AI' : 'Resep tersimpan' }}" :back="route('recipes.index')" class="hidden lg:flex">
        <button type="button" class="btn-ghost btn-sm"><x-icon name="cart" class="size-4" /> +{{ $missing->count() }} ke belanja</button>
        <button type="button" data-open="log-portion" class="btn-primary btn-sm">Catat porsi dimakan</button>
    </x-page-header>

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:gap-8">
        <div>
            <a href="{{ route('recipes.index') }}" class="grid size-10 place-items-center rounded-full bg-sand lg:hidden" aria-label="Kembali"><x-icon name="chevron-left" class="size-4" /></a>

            <div class="mt-4 flex flex-wrap gap-1.5">
                @if ($recipe['dibuat_ai'])<span class="badge bg-ai-soft text-ai">✦ Dibuat AI</span>@endif
                <span class="badge bg-sand text-muted">{{ $recipe['porsi'] }} porsi</span>
                <span class="badge bg-sand text-muted">{{ $recipe['menit'] }} menit</span>
            </div>
            <h1 class="mt-2 text-2xl font-extrabold lg:hidden">{{ $recipe['nama'] }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $recipe['deskripsi'] }}</p>

            <p class="stat-label mt-4">Gizi per porsi</p>
            <div class="mt-2 grid grid-cols-4 gap-2 text-center">
                @foreach ([[$recipe['kalori'], 'kkal'], [$recipe['protein'].' g', 'protein'], [$recipe['karbo'].' g', 'karbo'], [$recipe['lemak'].' g', 'lemak']] as [$v, $k])
                    <div class="rounded-[14px] bg-sand px-2 py-2.5"><b class="block text-base font-extrabold">{{ $v }}</b><span class="text-[11px] text-muted">{{ $k }}</span></div>
                @endforeach
            </div>

            <p class="mt-4 flex items-center gap-2 rounded-2xl bg-sand p-3 text-xs">
                <x-avatars /> Dimakan Hans dan Grevi hari ini · 1,5 porsi
            </p>

            {{-- HP: tab bahan / langkah --}}
            <div class="mt-5 lg:hidden">
                <div class="seg" data-choice>
                    <button type="button" class="is-active" onclick="document.getElementById('tab-bahan').classList.remove('hidden');document.getElementById('tab-langkah').classList.add('hidden')">Bahan</button>
                    <button type="button" onclick="document.getElementById('tab-bahan').classList.add('hidden');document.getElementById('tab-langkah').classList.remove('hidden')">Langkah</button>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <section id="tab-bahan" class="mt-3 lg:mt-0 lg:block lg:rounded-[20px] lg:border lg:border-line lg:p-4">
                <h2 class="mb-2 hidden font-bold lg:block">Bahan</h2>
                <ul class="divide-y divide-line">
                    @foreach ($recipe['bahan'] as $b)
                        <li class="flex items-center gap-2.5 py-2.5 text-sm">
                            @if ($b['ada'])
                                <x-icon name="check" class="size-4 text-olive-700" />
                            @else
                                <x-icon name="x" class="size-4 text-danger" />
                            @endif
                            <span @class(['flex-1', 'text-muted' => ! $b['ada']])>{{ $b['nama'] }}{{ $b['ada'] ? '' : ' · belum ada' }}</span>
                            @if ($b['tag'])<span class="badge bg-warn-soft text-warn">{{ $b['tag'] }}</span>@endif
                            <span class="text-xs text-muted">{{ $b['berat'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section id="tab-langkah" class="mt-3 hidden lg:mt-0 lg:block lg:rounded-[20px] lg:border lg:border-line lg:p-4">
                <h2 class="mb-3 hidden font-bold lg:block">Langkah</h2>
                <ol class="space-y-3">
                    @foreach ($recipe['langkah'] as $i => $step)
                        <li class="flex gap-3 text-sm">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-olive-100 text-xs font-bold text-olive-700">{{ $i + 1 }}</span>
                            <span>{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    <div class="sticky bottom-0 -mx-5 mt-6 grid grid-cols-[auto_1fr] gap-2 border-t border-line bg-white px-5 pt-3 pb-5 lg:hidden">
        <button type="button" class="btn-ghost">+{{ $missing->count() }} ke belanja</button>
        <button type="button" data-open="log-portion" class="btn-primary">Catat porsi</button>
    </div>

    @push('dialogs')
        <dialog id="log-portion" class="m-0 mt-auto w-full max-w-none rounded-t-[28px] bg-white p-0 lg:m-auto lg:max-w-md lg:rounded-3xl">
            <form method="dialog" class="p-5">
                <div class="mx-auto mb-4 h-[5px] w-11 rounded-full bg-sand-dark lg:hidden"></div>
                <h2 class="text-lg font-bold">Catat porsi dimakan</h2>
                <p class="text-xs text-muted">{{ $recipe['nama'] }} · {{ $recipe['kalori'] }} kkal/porsi</p>

                <span class="label mt-4">Porsi</span>
                <div class="pick grid grid-cols-4 gap-2" data-choice>
                    @foreach (['½', '1', '1½', '2'] as $p)
                        <button type="button" @class(['h-11 rounded-xl bg-sand font-bold', 'is-active' => $p === '1'])>{{ $p }}</button>
                    @endforeach
                </div>

                <label class="label mt-4" for="dimakan_pada">Dimakan pada</label>
                <input id="dimakan_pada" type="datetime-local" class="input" value="{{ now()->format('Y-m-d\TH:i') }}">

                <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                    @foreach ([[$recipe['kalori'], 'kkal'], [$recipe['protein'].' g', 'protein'], [$recipe['karbo'].' g', 'karbo'], [$recipe['lemak'].' g', 'lemak']] as [$v, $k])
                        <div class="rounded-[14px] bg-sand px-2 py-2"><b class="block font-extrabold">{{ $v }}</b><span class="text-[11px] text-muted">{{ $k }}</span></div>
                    @endforeach
                </div>

                <button class="btn-primary mt-5 w-full">Simpan</button>
            </form>
        </dialog>
    @endpush
</x-layouts.app>
