<x-layouts.guest title="Daftar">
    <a href="{{ route('login') }}" class="mb-4 grid size-9 place-items-center rounded-full bg-sand hover:bg-sand-dark" aria-label="Kembali"><x-icon name="chevron-left" class="size-4" /></a>

    <h2 class="text-2xl font-bold">Buat akun</h2>
    <p class="mt-1 text-sm text-muted">Satu akun bisa dipakai di semua HP anggota rumah.</p>

    <form method="GET" action="{{ route('household.setup') }}" class="mt-6 space-y-4">
        <div>
            <label class="label" for="nama">Nama</label>
            <input id="nama" name="nama" class="input" placeholder="Hans Vere Liem" autocomplete="name" required>
        </div>
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input" placeholder="hans@contoh.com" autocomplete="email" required>
        </div>
        <div>
            <label class="label" for="password">Kata sandi</label>
            <input id="password" type="password" name="password" minlength="8" class="input" placeholder="••••••••" autocomplete="new-password" required>
            <p class="mt-1.5 text-xs text-muted">Minimal 8 karakter.</p>
        </div>
        <div>
            <label class="label" for="password_confirmation">Ulangi kata sandi</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="input" placeholder="Ketik ulang kata sandi" autocomplete="new-password" required>
        </div>

        <button class="btn-primary w-full">Daftar</button>
    </form>

    <a href="{{ route('household.setup') }}" class="btn-ghost mt-3 w-full"><x-icon name="google" class="size-4" /> Daftar dengan Google</a>

    <p class="mt-6 text-center text-sm text-muted">Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-brand">Masuk</a></p>
</x-layouts.guest>
