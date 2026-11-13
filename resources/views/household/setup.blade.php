<x-layouts.guest title="Buat atau gabung rumah">
    <h2 class="text-2xl font-bold">Halo, {{ strtok($authUser['name'] ?? '', ' ') }}</h2>
    <p class="mt-1 text-sm text-muted">Satu kulkas dipakai bersama. Buat rumah baru atau gabung ke rumah temanmu.</p>

    <div class="mt-4">@include('partials.status')</div>

    <form method="POST" action="{{ route('household.store') }}" class="card-sand mt-6">
        @csrf
        <div class="flex items-start gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="home" class="size-4" /></span>
            <div>
                <p class="text-sm font-semibold">Buat rumah / kos baru</p>
                <p class="text-xs text-muted">Kamu jadi pemilik (owner)</p>
            </div>
        </div>
        <input name="name" value="{{ old('name') }}" class="input mt-3 bg-white" placeholder="Kos Melati Lt. 2" maxlength="80" aria-label="Nama rumah" required>
        @error('name') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        <button class="btn-primary mt-3 w-full">Buat rumah</button>
    </form>

    <div class="my-4 flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-sand-dark"></span>atau<span class="h-px flex-1 bg-sand-dark"></span></div>

    <form method="POST" action="{{ route('household.join') }}" class="card-sand">
        @csrf
        <div class="flex items-start gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-white text-olive-700"><x-icon name="users" class="size-4" /></span>
            <div>
                <p class="text-sm font-semibold">Gabung dengan kode</p>
                <p class="text-xs text-muted">Minta kode undangan ke pemilik rumah</p>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-6 gap-2" data-code-input>
            @for ($i = 0; $i < 6; $i++)
                <input name="kode[]" maxlength="1" inputmode="text" autocomplete="off" aria-label="Karakter {{ $i + 1 }}" required value="{{ old('kode.'.$i, $inviteCode[$i] ?? '') }}"
                       class="aspect-square w-full rounded-xl border-0 bg-white text-center text-lg font-bold uppercase focus:ring-2 focus:ring-ink focus:outline-none">
            @endfor
        </div>
        @error('invite_code') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        <button class="btn-ghost mt-3 w-full">Gabung</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button class="text-sm font-medium text-muted hover:text-ink">Bukan kamu? Keluar</button>
    </form>
</x-layouts.guest>
