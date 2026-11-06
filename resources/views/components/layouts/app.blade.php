@props(['title' => null, 'active' => null, 'bare' => false])
@php
    $user = \App\Support\DemoData::user();
    $household = \App\Support\DemoData::household();
    $nav = [
        ['dashboard', 'Beranda', 'home', null],
        ['fridge.index', 'Kulkas', 'fridge', null],
        ['menu.index', 'Menu & resep', 'book', null],
        ['shopping.index', 'Belanja', 'cart', 4],
        ['nutrition.index', 'Gizi & kalori', 'flame', null],
        ['waste.index', 'Food waste', 'chart', null],
        ['activity.index', 'Riwayat', 'history', null],
    ];
    $mobileNav = [
        ['dashboard', 'Beranda', 'home'],
        ['fridge.index', 'Kulkas', 'fridge'],
        null,
        ['menu.index', 'Menu', 'book'],
        ['shopping.index', 'Belanja', 'cart'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh">
    {{-- Sidebar (desktop ≥ 1024 px) --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[248px] flex-col gap-1 bg-olive-700 px-4 py-6 text-white lg:flex">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2 pb-4 text-[22px] font-extrabold">
            <x-icon name="leaf" class="size-6 text-olive-200" /> {{ config('app.name') }}
        </a>

        <button type="button" class="mb-3.5 flex w-full items-center gap-2.5 rounded-[14px] bg-white/10 px-3 py-2.5 text-left hover:bg-white/15">
            <span class="grid size-8 place-items-center rounded-full bg-olive-200 text-olive-700"><x-icon name="home" class="size-4" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-[11px] text-white/60">Rumah</span>
                <span class="block truncate text-sm font-bold">{{ $household['nama'] }}</span>
            </span>
            <x-icon name="chevron-down" class="size-4 text-white/60" />
        </button>

        <button type="button" data-open="add-item" class="mb-4 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-olive-200 text-sm font-extrabold text-olive-700 hover:bg-olive-300">
            <x-icon name="plus" class="size-4" /> Tambah bahan
        </button>

        <nav class="space-y-1 text-sm">
            @foreach ($nav as [$route, $label, $icon, $badge])
                <a href="{{ route($route) }}"
                   @class([
                       'flex h-11 items-center gap-3 rounded-xl px-3 font-semibold transition',
                       'bg-white/15 text-white' => $active === $route,
                       'text-white/65 hover:bg-white/10 hover:text-white' => $active !== $route,
                   ])>
                    <x-icon :name="$icon" class="size-5" />
                    <span class="flex-1">{{ $label }}</span>
                    @if ($badge)
                        <span class="rounded-full bg-brand px-[7px] py-px text-[11px] font-extrabold text-white">{{ $badge }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="mt-auto">
            <a href="{{ route('household.settings') }}"
               @class(['flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold', 'bg-white/15 text-white' => $active === 'household.settings', 'text-white/65 hover:bg-white/10' => $active !== 'household.settings'])>
                <x-icon name="users" class="size-5" /> Rumah &amp; akun
            </a>
            <div class="mt-2 flex items-center gap-2.5 border-t border-white/15 px-2 pt-3">
                <span class="grid size-8 place-items-center rounded-full bg-olive-200 text-[13px] font-extrabold text-olive-700">{{ mb_substr($user['nama'], 0, 1) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">{{ $user['nama'] }}</span>
                    <span class="block text-[11px] text-white/60">{{ ucfirst($user['role']) }}</span>
                </span>
                <a href="{{ route('login') }}" class="text-white/70 hover:text-white" title="Keluar"><x-icon name="logout" class="size-4" /></a>
            </div>
        </div>
    </aside>

    <main @class(['lg:pl-[248px]', 'pb-28 lg:pb-0' => ! $bare])>
        <div class="mx-auto w-full max-w-md px-5 pt-5 pb-6 lg:max-w-none lg:px-8 lg:pt-0 lg:pb-8">
            {{ $slot }}
        </div>
    </main>

    {{-- Bottom nav (HP) --}}
    @unless ($bare)
        <nav class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
            <div class="mx-auto flex max-w-md items-center justify-around rounded-t-[28px] bg-olive-700 px-2 pt-3 pb-[max(.9rem,env(safe-area-inset-bottom))] text-white">
                @foreach ($mobileNav as $item)
                    @if ($item === null)
                        <button type="button" data-open="add-item" aria-label="Tambah bahan"
                                class="-mt-9 grid size-[60px] place-items-center rounded-full border-4 border-white bg-olive-200 text-olive-700 shadow-[0_6px_16px_rgba(47,61,7,.3)]">
                            <x-icon name="plus" class="size-6" />
                        </button>
                    @else
                        @php [$route, $label, $icon] = $item; @endphp
                        <a href="{{ route($route) }}" @class(['flex w-[62px] flex-col items-center gap-1 text-[11px] font-semibold', 'text-white' => $active === $route, 'text-white/55' => $active !== $route])>
                            <x-icon :name="$icon" class="size-[22px]" />
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    @endunless

    @include('partials.add-item-dialog')
    @stack('dialogs')
</body>
</html>
