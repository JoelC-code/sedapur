@php use App\Support\DemoData as D; @endphp
<x-layouts.app title="Cek hasil scan" active="fridge.index" bare>
    <x-page-header title="Cek hasil scan" subtitle="{{ count($rows) }} item terdeteksi · Superindo, {{ now()->translatedFormat('j M') }}" :back="route('fridge.index')" />

    <div class="lg:grid lg:grid-cols-[320px_1fr] lg:gap-8">
        {{-- Foto struk (desktop) --}}
        <div class="hidden lg:block">
            <div class="sticky top-6 grid place-items-center rounded-3xl bg-sand p-8">
                <div class="w-[230px] -rotate-2 rounded-md bg-[#fcfcfa] px-[18px] py-4 font-mono text-[11px] leading-[1.7] text-[#3a3a3a] shadow-[0_8px_24px_rgba(0,0,0,.18)]">
                    <p class="text-center font-bold">SUPERINDO KERTAJAYA</p>
                    <hr class="my-1.5 border-dashed border-black/30">
                    @foreach ([['BAYAM HIJAU 2x', '6.000'], ['SUSU UHT 1L 2x', '18.500'], ['WORTEL IMPOR', '9.900'], ['KTG PLASTIK', '200']] as [$n, $p])
                        <p class="flex justify-between"><span>{{ $n }}</span><span>{{ $p }}</span></p>
                    @endforeach
                    <hr class="my-1.5 border-dashed border-black/30">
                    <p class="flex justify-between font-bold"><span>TOTAL</span><span>34.600</span></p>
                </div>
                <p class="mt-6 text-xs text-muted">Foto tersimpan di bucket privat</p>
            </div>
        </div>

        <form method="GET" action="{{ route('fridge.index') }}">
            <p class="flex gap-2 rounded-2xl bg-olive-100 p-3 text-xs">
                <x-icon name="sparkles" class="size-4 shrink-0 text-ai" />
                Ubah yang salah sebelum disimpan. Tanggal kedaluwarsa dari AI adalah perkiraan.
            </p>

            <div class="mt-3 space-y-2.5">
                @foreach ($rows as $i => [$nama, $emoji, $kat, $jumlah, $satuan, $harga, $exp, $src])
                    @php [$srcLabel, $srcCls] = D::sourceBadge($src); $keep = $src !== null; @endphp
                    <div @class(['rounded-2xl p-3', 'bg-sand' => $keep, 'bg-sand/50 opacity-60' => ! $keep])>
                        <div class="flex items-center gap-2.5">
                            <input type="checkbox" name="pilih[]" value="{{ $i }}" @checked($keep) class="size-6 rounded-lg border-2 border-sand-dark text-olive-700 focus:ring-olive-700">
                            <span class="text-xl">{{ $emoji }}</span>
                            <input value="{{ $nama }}" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] font-bold focus:ring-0" aria-label="Nama bahan">
                            @if ($src)<span class="badge {{ $srcCls }}">{{ $srcLabel }}</span>@endif
                            <x-icon name="edit" class="size-4 text-muted" />
                        </div>
                        @if ($keep)
                            <div class="mt-2.5 grid grid-cols-3 gap-2">
                                @foreach ([['Jumlah', $jumlah.' '.$satuan], ['Harga', D::rupiah($harga)], ['Kedaluwarsa', $exp]] as [$k, $v])
                                    <label class="rounded-[10px] bg-white px-2.5 py-1.5">
                                        <small class="block text-[10px] font-semibold text-muted">{{ $k }}</small>
                                        <input value="{{ $v }}" class="w-full border-0 bg-transparent p-0 text-[13px] font-bold focus:ring-0">
                                    </label>
                                @endforeach
                            </div>
                            @if ($src === 'estimasi_ai')
                                <p class="mt-2 text-[11px] text-ai">Gizi tidak ada di FatSecret / TKPI, jadi memakai estimasi AI.</p>
                            @endif
                        @else
                            <p class="mt-1 pl-9 text-[11px] text-muted">Bukan bahan makanan, tidak disimpan.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <button type="button" class="mt-3 w-full text-center text-sm font-semibold text-brand">+ Item lainnya</button>

            <div class="sticky bottom-0 -mx-5 mt-6 border-t border-line bg-white px-5 pt-3 pb-5 lg:static lg:mx-0 lg:border-0 lg:px-0">
                <div class="mb-3 flex justify-between text-sm"><span class="text-muted">Total belanja</span><b>{{ D::rupiah(34400) }}</b></div>
                <button class="btn-primary w-full">Simpan {{ collect($rows)->whereNotNull(7)->count() }} bahan ke kulkas</button>
            </div>
        </form>
    </div>
</x-layouts.app>
