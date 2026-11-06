@php $members = \App\Support\DemoData::household()['members']; @endphp
<div {{ $attributes->merge(['class' => 'flex -space-x-2']) }}>
    @foreach ($members as $m)
        <span class="grid size-7 place-items-center rounded-full {{ $m['warna'] }} text-[11px] font-bold text-white ring-2 ring-white" title="{{ $m['nama'] }}">{{ mb_substr($m['nama'], 0, 1) }}</span>
    @endforeach
</div>
