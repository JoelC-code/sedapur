{{-- Bottom sheet (HP) / modal (desktop) "Tambah bahan" --}}
<dialog id="add-item" class="m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 lg:m-auto lg:max-w-md lg:rounded-3xl">
    <div class="p-5">
        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-sand-dark lg:hidden"></div>
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-bold">Tambah bahan</h2>
                <p class="text-xs text-muted">Pilih cara paling cepat</p>
            </div>
            <button type="button" data-close class="hidden rounded-full p-1 text-muted hover:bg-sand lg:block" aria-label="Tutup"><x-icon name="x" /></button>
        </div>

        <div class="mt-4 space-y-2">
            @foreach ([
                ['receipt', 'Scan struk belanja', 'Nama, jumlah, harga, dan umur simpan langsung terbaca', 'struk'],
                ['camera', 'Scan isi kulkas', 'Untuk belanja di pasar atau pertama kali pakai', 'kulkas'],
            ] as [$icon, $label, $desc, $jenis])
                <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-olive-700"><x-icon :name="$icon" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2 text-sm font-semibold">{{ $label }} <span class="badge bg-ai-soft text-ai">AI</span></span>
                        <span class="block text-xs text-muted">{{ $desc }}</span>
                    </span>
                    <x-icon name="chevron-right" class="size-4 text-muted" />
                    {{-- Kamera HP langsung dari browser --}}
                    <input type="file" accept="image/*" capture="environment" class="sr-only" data-scan="{{ route('scan.processing', ['jenis' => $jenis]) }}">
                </label>
            @endforeach

            <a href="{{ route('items.manual') }}" class="flex items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="keyboard" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">Input manual</span>
                    <span class="block text-xs text-muted">Ketik nama, dibantu daftar bahan bergizi</span>
                </span>
                <x-icon name="chevron-right" class="size-4 text-muted" />
            </a>
        </div>

        <button type="button" data-close class="btn mt-4 w-full bg-sand text-ink hover:bg-sand-dark lg:hidden">Batal</button>
    </div>
</dialog>
