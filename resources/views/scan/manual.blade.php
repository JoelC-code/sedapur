<x-layouts.app title="Input manual" active="fridge.index" bare>
    <x-page-header title="Input manual" :back="route('fridge.index')" />

    <form method="GET" action="{{ route('fridge.index') }}" class="space-y-4 lg:max-w-2xl">
        <div>
            <label class="label" for="nama">Nama bahan</label>
            <div class="relative">
                <input id="nama" name="nama" list="nutrition-foods" class="input pr-24" value="Telur ayam" autocomplete="off">
                <span class="badge absolute top-1/2 right-3 -translate-y-1/2 bg-ok-soft text-olive-700">TKPI</span>
            </div>
            <datalist id="nutrition-foods">
                @foreach (['Telur ayam', 'Telur bebek', 'Tahu putih', 'Tempe', 'Bayam', 'Kangkung', 'Wortel', 'Tomat', 'Dada ayam', 'Susu UHT', 'Beras'] as $opt)
                    <option value="{{ $opt }}"></option>
                @endforeach
            </datalist>
            <p class="mt-1.5 text-xs text-muted">Gizi ditemukan · 154 kkal / 100 g</p>
        </div>

        <div>
            <span class="label">Kategori</span>
            <div class="pick flex flex-wrap gap-2" data-choice>
                @foreach ($categories as $key => $cat)
                    <button type="button" @class(['chip', 'is-active' => $key === 'susu_telur'])>{{ $cat['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <span class="label">Jumlah</span>
                <div class="flex h-[52px] items-center justify-between rounded-[14px] bg-sand px-2">
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-white" onclick="const i=this.nextElementSibling;i.value=Math.max(0,+i.value-1)">−</button>
                    <input name="jumlah" type="number" value="10" class="w-12 border-0 bg-transparent p-0 text-center font-bold focus:ring-0">
                    <button type="button" class="grid size-9 place-items-center rounded-full bg-white" onclick="const i=this.previousElementSibling;i.value=+i.value+1">+</button>
                </div>
            </div>
            <div>
                <label class="label" for="satuan">Satuan</label>
                <select id="satuan" name="satuan" class="input">
                    @foreach (['butir', 'buah', 'ikat', 'kotak', 'pack', 'g', 'kg', 'botol'] as $s)
                        <option>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="berat">Berat (g)</label>
                <input id="berat" name="berat_gram" type="number" class="input" value="600">
            </div>
            <div>
                <label class="label" for="harga">Harga · opsional</label>
                <input id="harga" name="harga" class="input" value="Rp 28.000">
            </div>
            <div>
                <label class="label" for="tgl_beli">Tgl beli</label>
                <input id="tgl_beli" name="tgl_beli" type="date" class="input" value="{{ now()->toDateString() }}">
            </div>
            <div>
                <label class="label" for="tgl_exp">Kedaluwarsa</label>
                <input id="tgl_exp" name="tgl_kedaluwarsa" type="date" class="input" value="{{ now()->addDays(14)->toDateString() }}">
            </div>
        </div>
        <p class="text-xs text-muted">Saran: telur tahan ±14 hari di kulkas.</p>

        <button class="btn-primary w-full lg:w-auto">Tambah ke kulkas</button>
    </form>
</x-layouts.app>
