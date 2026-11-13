@php $members = \App\Support\HouseholdMembers::forCurrentHousehold(); @endphp
@if ($members !== [])
    <a href="{{ route('household.settings') }}" {{ $attributes->merge(['class' => 'flex -space-x-1.5']) }} aria-label="Anggota rumah: {{ collect($members)->pluck('nama')->join(', ') }}">
        @foreach ($members as $m)
            <span class="grid size-8 place-items-center rounded-full {{ $m['warna'] }} text-xs font-bold text-white ring-2 ring-white" title="{{ $m['nama'] }}">{{ mb_strtoupper(mb_substr($m['nama'], 0, 1)) }}</span>
        @endforeach
    </a>
@endif
