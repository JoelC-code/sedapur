<x-layouts.app title="Scan diproses" active="fridge.index" bare>
    <div class="-mx-5 -mt-5 flex min-h-dvh flex-col bg-[#1d2010] px-5 pt-5 pb-8 text-white lg:mx-0 lg:mt-6 lg:min-h-[calc(100dvh-3rem)] lg:rounded-3xl">
        <div class="flex items-center">
            <a href="{{ route('fridge.index') }}" class="grid size-10 place-items-center rounded-full bg-white/10" aria-label="Tutup"><x-icon name="x" class="size-4" /></a>
            <p class="flex-1 text-center font-bold">Scan {{ $jenis === 'kulkas' ? 'isi kulkas' : 'struk' }}</p>
            <span class="size-10"></span>
        </div>

        <div class="relative mx-auto mt-10 flex justify-center">
            @if ($jenis === 'kulkas')
                <div class="grid w-64 grid-cols-3 gap-3 rounded-2xl bg-white/5 p-5 text-4xl" aria-hidden="true">
                    <span>🥬</span><span>🥛</span><span>🍅</span><span>🥚</span><span>🍗</span><span>🥕</span>
                </div>
            @else
                <div class="w-[230px] -rotate-2 rounded-md bg-[#fcfcfa] px-[18px] py-4 font-mono text-[11px] leading-[1.7] text-[#3a3a3a] shadow-[0_8px_24px_rgba(0,0,0,.25)]">
                    <p class="text-center font-bold">SUPERINDO KERTAJAYA</p>
                    <p class="text-center">{{ now()->format('d/m/Y H:i') }}</p>
                    <hr class="my-1.5 border-dashed border-black/30">
                    @foreach ([['BAYAM HIJAU 2x', '6.000'], ['SUSU UHT 1L 2x', '18.500'], ['WORTEL IMPOR', '9.900'], ['TELUR AYAM 6', '28.000'], ['TAHU PUTIH', '5.000'], ['KTG PLASTIK', '200']] as [$n, $p])
                        <p class="flex justify-between"><span>{{ $n }}</span><span>{{ $p }}</span></p>
                    @endforeach
                    <hr class="my-1.5 border-dashed border-black/30">
                    <p class="flex justify-between font-bold"><span>TOTAL</span><span>100.400</span></p>
                </div>
            @endif
            <span class="absolute inset-x-0 top-1/2 h-[3px] animate-pulse rounded bg-olive-200 shadow-[0_0_18px_4px_rgba(221,235,143,.9)]"></span>
        </div>

        <div class="mx-auto mt-auto w-full max-w-md rounded-2xl bg-white/10 p-4">
            <p class="flex items-center gap-2 font-bold"><x-icon name="sparkles" class="size-4 text-olive-200" /> Membaca {{ $jenis === 'kulkas' ? 'foto kulkas' : 'struk' }}…</p>
            <p class="mt-1 text-xs text-white/70">Gemini sedang mengambil nama item, jumlah, harga, dan perkiraan umur simpan.</p>
            <ul class="mt-3 space-y-1.5 text-sm">
                <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-olive-200" /> Foto diunggah</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-olive-200" /> Membaca item</li>
                <li class="flex items-center gap-2 text-white/50"><span class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> Mencocokkan data gizi</li>
            </ul>
        </div>

        <a href="{{ route('fridge.index') }}" class="btn mx-auto mt-4 w-full max-w-md bg-white/10 text-white hover:bg-white/15">Batalkan</a>
    </div>

    <script>
        // Demo: setelah "diproses", lanjut ke tabel konfirmasi.
        setTimeout(() => window.location.href = @json(route('scan.review')), 3500);
    </script>
</x-layouts.app>
