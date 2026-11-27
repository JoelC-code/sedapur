@php
    $missing = collect($recipe['bahan'])->where('ada', false);
    $formatMacro = fn (float|int $value): string => str_replace('.', ',', (string) round((float) $value, 1));
@endphp
<x-layouts.app :title="$recipe['nama']" active="menu.index" bare>
    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

    @error('portions')
        <div class="mb-4 rounded-2xl bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</div>
    @enderror

    <x-page-header :title="$recipe['nama']" subtitle="Menu & resep · {{ $recipe['tersimpan'] ? 'Resep tersimpan' : ($recipe['dibuat_ai'] ? 'Dibuat AI' : 'Resep rumah') }}" :back="route('recipes.index')" class="hidden lg:flex">
        <form method="POST" action="{{ route('recipes.save', $recipe['id']) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="is_saved" value="{{ $recipe['tersimpan'] ? 0 : 1 }}">
            <button class="btn-ghost btn-sm">{{ $recipe['tersimpan'] ? 'Tersimpan' : 'Simpan resep' }}</button>
        </form>
        @if ($missing->isNotEmpty())
            <form method="POST" action="{{ route('recipes.shopping', $recipe['id']) }}">
                @csrf
                <button class="btn-ghost btn-sm"><x-icon name="cart" class="size-4" /> +{{ $missing->count() }} ke belanja</button>
            </form>
        @endif
        <button type="button" data-open="log-portion" class="btn-primary btn-sm"><x-icon name="utensils" class="size-4" /> Catat porsi dimakan</button>
    </x-page-header>

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:gap-8">
        <div>
            <div class="flex items-center justify-between lg:hidden">
                <a href="{{ route('recipes.index') }}" class="grid size-10 place-items-center rounded-full bg-sand" aria-label="Kembali"><x-icon name="chevron-left" class="size-4" /></a>
                <form method="POST" action="{{ route('recipes.save', $recipe['id']) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_saved" value="{{ $recipe['tersimpan'] ? 0 : 1 }}">
                    <button class="chip bg-sand">{{ $recipe['tersimpan'] ? 'Tersimpan' : 'Simpan' }}</button>
                </form>
            </div>

            <div class="mt-4 flex flex-wrap gap-1.5">
                @if ($recipe['dibuat_ai'])<span class="badge bg-ai-soft text-ai">✦ Dibuat AI</span>@endif
                @if ($recipe['tersimpan'])<span class="badge bg-ok-soft text-brand-dark">Tersimpan</span>@endif
                <span class="badge bg-sand text-muted">{{ $recipe['porsi'] }} porsi</span>
                <span class="badge bg-sand text-muted">{{ $recipe['menit'] }} menit</span>
            </div>
            <h1 class="mt-2 text-2xl font-extrabold lg:hidden">{{ $recipe['nama'] }}</h1>
            @if ($recipe['deskripsi'] !== '')
                <p class="mt-1 text-sm text-muted">{{ $recipe['deskripsi'] }}</p>
            @endif

            <p class="stat-label mt-4">Gizi per porsi (estimasi AI)</p>
            <div class="mt-2 grid grid-cols-4 gap-2 text-center">
                @foreach ([[$recipe['kalori'], 'kkal'], [$formatMacro($recipe['protein']).' g', 'protein'], [$formatMacro($recipe['karbo']).' g', 'karbo'], [$formatMacro($recipe['lemak']).' g', 'lemak']] as [$v, $k])
                    <div class="rounded-[14px] bg-sand px-2 py-2.5"><b class="block text-base font-extrabold">{{ $v }}</b><span class="text-[11px] text-muted">{{ $k }}</span></div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between gap-2 rounded-2xl bg-sand p-3 text-xs">
                <span class="text-muted">
                    @if ($missing->isEmpty())
                        Semua bahan tersedia di kulkas rumahmu.
                    @else
                        {{ count($recipe['bahan']) - $missing->count() }} dari {{ count($recipe['bahan']) }} bahan tersedia di kulkas.
                    @endif
                </span>
                <form method="POST" action="{{ route('recipes.destroy', $recipe['id']) }}" data-confirm="Hapus resep {{ $recipe['nama'] }}?">
                    @csrf @method('DELETE')
                    <button class="font-semibold text-danger hover:underline">Hapus</button>
                </form>
            </div>

            {{-- HP: tab bahan / langkah --}}
            <div class="mt-5 lg:hidden">
                <div class="seg" data-choice data-tab-group>
                    <button type="button" class="is-active" data-tab-target="tab-bahan">Bahan</button>
                    <button type="button" data-tab-target="tab-langkah">Langkah</button>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <section id="tab-bahan" data-tab-panel class="mt-3 lg:mt-0 lg:block lg:rounded-[20px] lg:border lg:border-line lg:p-4">
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

            <section id="tab-langkah" data-tab-panel class="mt-3 hidden lg:mt-0 lg:block lg:rounded-[20px] lg:border lg:border-line lg:p-4">
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

    <div class="sticky bottom-0 -mx-5 mt-6 flex gap-2 border-t border-line bg-white px-5 pt-3 pb-5 lg:hidden">
        @if ($missing->isNotEmpty())
            <form method="POST" action="{{ route('recipes.shopping', $recipe['id']) }}">
                @csrf
                <button class="btn-ghost">+{{ $missing->count() }} ke belanja</button>
            </form>
        @endif
        <button type="button" data-open="log-portion" class="btn-primary flex-1">Catat porsi dimakan</button>
    </div>

    @push('dialogs')
        <dialog id="log-portion" class="m-0 mt-auto w-full max-w-none rounded-t-[28px] bg-white p-0 lg:m-auto lg:max-w-md lg:rounded-3xl">
            <form method="POST" action="{{ route('recipes.consume', $recipe['id']) }}" class="p-5"
                data-portion-form
                data-calories="{{ $recipe['kalori'] }}"
                data-protein="{{ $recipe['protein'] }}"
                data-carbs="{{ $recipe['karbo'] }}"
                data-fat="{{ $recipe['lemak'] }}">
                @csrf
                <input type="hidden" name="portions" value="1">
                <div class="mx-auto mb-4 h-[5px] w-11 rounded-full bg-sand-dark lg:hidden"></div>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold">Catat porsi dimakan</h2>
                        <p class="text-xs text-muted">{{ $recipe['nama'] }} · {{ $recipe['kalori'] }} kkal/porsi</p>
                    </div>
                    <button type="button" data-close class="hidden rounded-full p-1 text-muted hover:bg-sand lg:block" aria-label="Tutup"><x-icon name="x" /></button>
                </div>

                <span class="label mt-4">Porsi</span>
                <div class="pick grid grid-cols-4 gap-2" data-choice>
                    @foreach ([['0.5', '½'], ['1', '1'], ['1.5', '1½'], ['2', '2']] as [$val, $label])
                        <button type="button" data-portion-value="{{ $val }}" @class(['h-11 rounded-xl bg-sand font-bold', 'is-active' => $val === '1'])>{{ $label }}</button>
                    @endforeach
                </div>

                <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                    <div class="rounded-[14px] bg-sand px-2 py-2"><b class="block font-extrabold" data-macro="calories">{{ $recipe['kalori'] }}</b><span class="text-[11px] text-muted">kkal</span></div>
                    <div class="rounded-[14px] bg-sand px-2 py-2"><b class="block font-extrabold" data-macro="protein">{{ $formatMacro($recipe['protein']) }} g</b><span class="text-[11px] text-muted">protein</span></div>
                    <div class="rounded-[14px] bg-sand px-2 py-2"><b class="block font-extrabold" data-macro="carbs">{{ $formatMacro($recipe['karbo']) }} g</b><span class="text-[11px] text-muted">karbo</span></div>
                    <div class="rounded-[14px] bg-sand px-2 py-2"><b class="block font-extrabold" data-macro="fat">{{ $formatMacro($recipe['lemak']) }} g</b><span class="text-[11px] text-muted">lemak</span></div>
                </div>

                <p class="mt-3 text-xs text-muted">Akan langsung tercatat ke halaman Gizi & kalori hari ini.</p>

                <button class="btn-primary mt-5 w-full" data-loading-label="Menyimpan…">Simpan ke Gizi & kalori</button>
            </form>
        </dialog>
    @endpush
</x-layouts.app>
