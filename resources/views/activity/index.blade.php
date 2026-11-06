@php
    $badge = [
        'ditambah' => ['Ditambah', 'bg-ok-soft text-olive-700'],
        'diubah' => ['Diubah', 'bg-info-soft text-info'],
        'dimasak' => ['Dimasak', 'bg-warn-soft text-warn'],
        'dibuang' => ['Dibuang', 'bg-danger-soft text-danger'],
    ];
@endphp
<x-layouts.app title="Riwayat aktivitas" active="activity.index">
    <x-page-header title="Riwayat aktivitas" subtitle="Siapa menambah, memasak, atau membuang bahan" :back="route('dashboard')">
        <x-avatars class="hidden lg:flex" />
    </x-page-header>

    <div class="no-scrollbar -mx-5 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:px-0">
        <a href="{{ route('activity.index') }}" class="{{ $filter === 'semua' ? 'chip-active' : 'chip' }}">Semua</a>
        @foreach ($badge as $key => [$label])
            <a href="{{ route('activity.index', ['aksi' => $key]) }}" class="{{ $filter === $key ? 'chip-active' : 'chip' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- HP: timeline --}}
    <div class="mt-4 lg:hidden">
        @foreach ($groups as $g)
            <p class="stat-label mt-4 mb-2">{{ $g['hari'] }}</p>
            @foreach ($g['items'] as [$who, $what, $note, $aksi, $time])
                @continue($filter !== 'semua' && $filter !== $aksi)
                <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        @include('partials.member-dot', ['name' => $who])
                        @unless ($loop->last)<span class="mt-1 w-0.5 flex-1 bg-line"></span>@endunless
                    </div>
                    <div class="min-w-0 flex-1 pb-4">
                        <div class="flex justify-between gap-2">
                            <p class="text-sm"><b>{{ $who }}</b> {{ $what }}</p>
                            <span class="text-[11px] text-muted">{{ $time }}</span>
                        </div>
                        <p class="text-xs text-muted">{{ $note }}</p>
                        <span class="badge mt-1 {{ $badge[$aksi][1] }}">{{ $badge[$aksi][0] }}</span>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    {{-- Desktop: tabel --}}
    <table class="mt-5 hidden w-full text-sm lg:table">
        <thead>
            <tr class="border-b border-line text-left text-[11px] tracking-[.07em] text-muted uppercase">
                @foreach (['Waktu', 'Anggota', 'Aktivitas', 'Keterangan', 'Aksi'] as $th)
                    <th class="px-3 py-2.5 font-bold">{{ $th }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $g)
                <tr><td colspan="5" class="bg-sand px-3 py-2 text-[11px] font-bold tracking-[.07em] text-muted uppercase">{{ $g['hari'] }}</td></tr>
                @foreach ($g['items'] as [$who, $what, $note, $aksi, $time])
                    @continue($filter !== 'semua' && $filter !== $aksi)
                    <tr class="border-b border-line">
                        <td class="px-3 py-3 text-muted">{{ $time }}</td>
                        <td class="px-3 py-3"><span class="flex items-center gap-2">@include('partials.member-dot', ['name' => $who]) {{ $who }}</span></td>
                        <td class="px-3 py-3 font-semibold">{{ ucfirst($what) }}</td>
                        <td class="px-3 py-3 text-muted">{{ $note }}</td>
                        <td class="px-3 py-3"><span class="badge {{ $badge[$aksi][1] }}">{{ $badge[$aksi][0] }}</span></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</x-layouts.app>
