<x-layouts.app title="Rumah & akun" active="household.settings">
    <x-page-header title="Rumah & akun" subtitle="Kelola profil, rumah, anggota, dan pengaturan email" :back="route('dashboard')" />

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
        <div class="space-y-4">
            <section class="card-sand flex items-center gap-3">
                <span class="grid size-[52px] place-items-center rounded-full bg-olive-700 text-lg font-extrabold text-white">{{ mb_substr($user['nama'], 0, 1) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $user['nama'] }}</p>
                    <p class="text-xs text-muted">{{ $user['email'] }}</p>
                </div>
                <button class="grid size-10 place-items-center rounded-full bg-white" aria-label="Ubah profil"><x-icon name="edit" class="size-4" /></button>
            </section>

            <section class="card-sand">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="stat-label">Rumah</p>
                        <p class="font-bold">{{ $household['nama'] }}</p>
                    </div>
                    <span class="badge bg-ok-soft text-olive-700">Owner</span>
                </div>
                <p class="stat-label mt-3">Kode undangan</p>
                <div class="mt-1 flex items-center gap-2">
                    <p class="flex-1 text-2xl font-extrabold tracking-[.15em]">{{ $household['kode_undangan'] }}</p>
                    <button type="button" data-copy="{{ $household['kode_undangan'] }}" class="grid size-10 place-items-center rounded-full bg-white" aria-label="Salin kode"><x-icon name="copy" class="size-4" /></button>
                    <button type="button" class="grid size-10 place-items-center rounded-full bg-brand text-white" aria-label="Bagikan kode"
                            onclick="navigator.share?.({ title: 'Gabung ke {{ $household['nama'] }}', text: 'Kode undangan: {{ $household['kode_undangan'] }}' })"><x-icon name="share" class="size-4" /></button>
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <section class="card">
                <h2 class="mb-1 font-bold">Anggota · {{ count($household['members']) }}</h2>
                <ul class="divide-y divide-line">
                    @foreach ($household['members'] as $m)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="grid size-8 place-items-center rounded-full {{ $m['warna'] }} text-[13px] font-extrabold text-white">{{ mb_substr($m['nama'], 0, 1) }}</span>
                            <span class="flex-1">
                                <span class="block text-sm font-bold">{{ $m['lengkap'] }}</span>
                                <span class="text-[11px] text-muted">Gabung {{ $m['joined_at'] }}</span>
                            </span>
                            <span @class(['badge', 'bg-ok-soft text-olive-700' => $m['role'] === 'owner', 'bg-sand text-muted' => $m['role'] !== 'owner'])>{{ ucfirst($m['role']) }}</span>
                            @if ($m['role'] !== 'owner')<button class="text-muted" aria-label="Opsi"><x-icon name="more" class="size-4" /></button>@endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <label class="card-sand flex cursor-pointer items-center gap-3">
                <x-icon name="mail" class="size-5 text-olive-700" />
                <span class="flex-1">
                    <span class="block text-sm font-bold">Pengingat email harian</span>
                    <span class="text-[11px] text-muted">Setiap pagi jam 07.00</span>
                </span>
                <input type="checkbox" checked class="peer sr-only">
                <span class="relative h-7 w-[46px] rounded-full bg-sand-dark transition peer-checked:bg-olive-700 after:absolute after:top-[3px] after:left-[3px] after:size-[22px] after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-[18px]"></span>
            </label>
            <a href="{{ route('preview.email') }}" class="block text-center text-xs font-semibold text-brand">Lihat contoh email pengingat</a>

            <a href="{{ route('login') }}" class="btn-danger w-full"><x-icon name="logout" class="size-4" /> Keluar akun</a>
        </div>
    </div>
</x-layouts.app>
