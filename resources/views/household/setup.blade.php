<x-layouts.guest title="Buat atau gabung rumah">
    <h2 class="text-2xl font-bold">Halo, {{ $user['panggilan'] }} 👋</h2>
    <p class="mt-1 text-sm text-muted">Satu kulkas dipakai bersama. Buat rumah baru atau gabung ke rumah temanmu.</p>

    <form method="GET" action="{{ route('dashboard') }}" class="card-sand mt-6">
        <div class="flex items-start gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="home" class="size-4" /></span>
            <div>
                <p class="text-sm font-semibold">Buat rumah / kos baru</p>
                <p class="text-xs text-muted">Kamu jadi pemilik (owner)</p>
            </div>
        </div>
        <input name="nama" class="input mt-3 bg-white" placeholder="Kos Melati Lt. 2" required>
        <button class="btn-primary mt-3 w-full">Buat rumah</button>
    </form>

    <div class="my-4 flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-sand-dark"></span>atau<span class="h-px flex-1 bg-sand-dark"></span></div>

    <form method="GET" action="{{ route('dashboard') }}" class="card-sand">
        <div class="flex items-start gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="users" class="size-4" /></span>
            <div>
                <p class="text-sm font-semibold">Gabung dengan kode</p>
                <p class="text-xs text-muted">Minta kode undangan ke pemilik rumah</p>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-6 gap-2" data-code-input>
            @for ($i = 0; $i < 6; $i++)
                <input name="kode[]" maxlength="1" inputmode="text" autocomplete="off" aria-label="Karakter {{ $i + 1 }}"
                       class="aspect-square w-full rounded-xl border-0 bg-white text-center text-lg font-bold uppercase focus:ring-2 focus:ring-ink focus:outline-none">
            @endfor
        </div>
        <button class="btn-ghost mt-3 w-full">Gabung</button>
    </form>
</x-layouts.guest>
