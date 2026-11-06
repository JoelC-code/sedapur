<x-layouts.app title="Scan gagal" active="fridge.index" bare>
    <div class="mx-auto flex min-h-[calc(100dvh-2.5rem)] max-w-md flex-col">
        <div class="flex items-center">
            <a href="{{ route('fridge.index') }}" class="grid size-10 place-items-center rounded-full bg-sand" aria-label="Tutup"><x-icon name="x" class="size-4" /></a>
            <p class="flex-1 text-center font-bold">Scan struk</p>
            <span class="size-10"></span>
        </div>

        <div class="mt-16 text-center">
            <span class="mx-auto grid size-20 place-items-center rounded-3xl bg-danger-soft text-danger"><x-icon name="receipt" class="size-9" /></span>
            <h1 class="mt-5 text-2xl font-extrabold">Struk tidak terbaca</h1>
            <p class="mx-auto mt-2 max-w-xs text-sm text-muted">Foto terlalu gelap atau terlipat. Coba lagi dengan tips berikut.</p>
        </div>

        <ul class="card-sand mt-6 space-y-2 text-sm">
            @foreach (['Letakkan struk di permukaan rata', 'Pastikan cahaya cukup, tanpa bayangan', 'Semua baris item masuk ke foto'] as $tip)
                <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-olive-700" /> {{ $tip }}</li>
            @endforeach
        </ul>

        <div class="mt-auto space-y-2 pt-8">
            <label class="btn-primary w-full cursor-pointer">
                <x-icon name="camera" class="size-4" /> Foto ulang
                <input type="file" accept="image/*" capture="environment" class="sr-only" data-scan="{{ route('scan.processing') }}">
            </label>
            <a href="{{ route('items.manual') }}" class="btn-ghost w-full">Input manual saja</a>
        </div>
    </div>
</x-layouts.app>
