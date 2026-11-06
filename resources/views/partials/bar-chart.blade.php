{{-- $bars: [[label, nilai], ...]; batang terakhir disorot oranye. --}}
@php
    $max = max(array_column($bars, 1)) ?: 1;
    $highlight = $highlight ?? array_key_last($bars);
@endphp
<div class="flex items-end gap-2 {{ $height ?? 'h-32' }}">
    @foreach ($bars as $i => [$label, $value])
        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
            <div @class(['w-full rounded-md', 'bg-brand' => $i === $highlight, 'bg-sand-dark' => $i !== $highlight])
                 style="height: {{ max(3, round($value / $max * 100)) }}%" title="{{ $label }}: {{ number_format($value, 0, ',', '.') }}"></div>
            <span class="text-[10px] text-muted">{{ $label }}</span>
        </div>
    @endforeach
</div>
