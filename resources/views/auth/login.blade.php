<x-layouts.guest title="Masuk">
    <h2 class="text-2xl font-bold">Masuk</h2>
    <p class="mt-1 text-sm text-muted">Lanjutkan mengelola kulkas bersama.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        @include('partials.status')
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" placeholder="hans@contoh.com" autocomplete="email" required autofocus>
            @error('email') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password">Kata sandi</label>
            <div class="relative">
                <input id="password" type="password" name="password" class="input pr-16" placeholder="••••••••" autocomplete="current-password" required>
                <button type="button" class="absolute inset-y-0 right-4 text-xs font-semibold text-brand" onclick="const i=this.previousElementSibling;i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'Lihat':'Sembunyikan'">Lihat</button>
            </div>
            @error('password') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
        </div>

        <button class="btn-primary w-full">Masuk</button>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-sand-dark"></span>atau<span class="h-px flex-1 bg-sand-dark"></span></div>

    <button type="button" class="btn-ghost w-full opacity-60" disabled title="Login Google menyusul"><x-icon name="google" class="size-4" /> Masuk dengan Google (segera)</button>

    <p class="mt-6 text-center text-sm text-muted">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-brand">Daftar</a></p>
</x-layouts.guest>
