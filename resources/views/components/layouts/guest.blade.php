@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-white">
    <div class="grid min-h-dvh lg:grid-cols-2">
        {{-- Panel ilustrasi --}}
        <section class="relative flex flex-col bg-olive-100 px-6 pt-10 pb-8 lg:px-14 lg:py-12">
            <a href="{{ route('login') }}" class="hidden items-center gap-2 text-lg font-bold text-ink lg:flex">
                <x-icon name="leaf" class="size-5 text-olive-700" /> {{ config('app.name') }}
            </a>

            <div class="flex flex-1 flex-col items-center justify-center lg:items-start">
                <span class="grid size-20 place-items-center rounded-full bg-olive-200 text-olive-700 lg:size-24" aria-hidden="true">
                    <x-icon name="fridge" class="size-9 lg:size-11" />
                </span>

                <p class="mt-6 flex items-center gap-2 text-2xl font-extrabold text-ink lg:hidden">
                    <x-icon name="leaf" class="size-6 text-olive-700" /> {{ config('app.name') }}
                </p>
                <p class="mt-1 text-sm text-muted lg:hidden">Habiskan sebelum kedaluwarsa.</p>

                <h1 class="mt-8 hidden text-4xl leading-tight font-extrabold text-ink lg:block">
                    Satu kulkas, <br>dipakai bersama. <br>Habiskan sebelum <br>kedaluwarsa.
                </h1>
                <p class="mt-4 hidden max-w-sm text-sm text-muted lg:block">
                    Scan struk belanja, pantau tanggal kedaluwarsa, dan lihat berapa uang yang terbuang setiap bulan.
                </p>
            </div>

            <p class="hidden text-xs text-muted lg:block">Kelompok 7 · Cloud Computing</p>
        </section>

        {{-- Form --}}
        <section class="flex items-start justify-center px-6 py-8 lg:items-center lg:px-14">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </section>
    </div>
</body>
</html>
