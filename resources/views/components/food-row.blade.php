@props(['item', 'href' => null, 'active' => false])
@php [$label, $cls] = \App\Support\DemoData::expiryBadge($item['sisa_hari']); @endphp
<a href="{{ $href ?? route('fridge.show', $item['id']) }}"
   @class(['flex items-center gap-3 rounded-2xl p-3 transition', 'bg-olive-100 ring-2 ring-olive-200' => $active, 'bg-sand hover:bg-sand-dark' => ! $active])>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-[15px] font-bold">{{ $item['nama'] }}</span>
        <span class="mt-0.5 block truncate text-xs text-muted">
            {{ \App\Support\DemoData::CATEGORIES[$item['kategori']]['label'] ?? 'Lainnya' }} · {{ $item['jumlah'] }} {{ $item['satuan'] }} · {{ \App\Support\DemoData::number($item['berat_gram']) }} g · {{ \App\Support\DemoData::rupiah($item['harga']) }}
        </span>
    </span>
    <span class="days {{ $cls }}">{{ $label }}</span>
</a>
