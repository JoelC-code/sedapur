@props(['title', 'subtitle' => null, 'back' => null])
{{-- HP: judul halaman biasa. Desktop: bilah atas 76 px dengan garis bawah (sesuai UI kit). --}}
<header {{ $attributes->merge(['class' => 'mb-5 flex items-center gap-3 lg:-mx-8 lg:mb-7 lg:h-[76px] lg:gap-4 lg:border-b lg:border-line lg:px-8']) }}>
    @if ($back)
        <a href="{{ $back }}" class="grid size-10 shrink-0 place-items-center rounded-full bg-sand hover:bg-sand-dark" aria-label="Kembali">
            <x-icon name="chevron-left" class="size-4" />
        </a>
    @endif
    <div class="min-w-0 flex-1">
        <h1 class="truncate text-lg font-extrabold lg:text-[22px] lg:tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="truncate text-xs text-muted lg:text-[13px]">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</header>
