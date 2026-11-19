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
            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="receipt" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 text-sm font-semibold">Scan struk belanja <span class="badge bg-ai-soft text-ai">AI</span></span>
                    <span class="block text-xs text-muted">Nama, jumlah, harga, dan umur simpan langsung terbaca</span>
                </span>
                <x-icon name="chevron-right" class="size-4 text-muted" />
                {{-- Kamera HP langsung dari browser --}}
                <input type="file" name="receipt" accept="image/*" capture="environment" class="sr-only" data-scan="{{ route('scan.upload') }}">
            </label>

            {{-- Scan kulkas belum punya backend; jangan kirim ke alur struk --}}
            <div class="flex items-center gap-3 rounded-2xl bg-sand p-3 opacity-60" aria-disabled="true">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="camera" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 text-sm font-semibold">Scan isi kulkas <span class="badge bg-sand-dark text-muted">Segera hadir</span></span>
                    <span class="block text-xs text-muted">Untuk belanja di pasar atau pertama kali pakai</span>
                </span>
            </div>

            <a href="{{ route('scan.manual') }}" class="flex items-center gap-3 rounded-2xl bg-sand p-3 hover:bg-sand-dark">
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
