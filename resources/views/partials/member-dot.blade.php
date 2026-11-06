@php
    $colors = ['Hans' => 'bg-olive-700', 'Grevi' => 'bg-brand', 'Joel' => 'bg-info'];
@endphp
<span class="grid size-6 shrink-0 place-items-center rounded-full {{ $colors[$name] ?? 'bg-muted' }} text-[10px] font-bold text-white">{{ mb_substr($name, 0, 1) }}</span>
