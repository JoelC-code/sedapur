@php $w = app(\App\Services\WasteSummaryService::class)->forCurrentHousehold(); @endphp
<section class="rounded-2xl bg-olive-100 p-4 ring-1 ring-olive-200">
    <p class="flex items-center gap-2 text-sm font-bold"><x-icon name="sparkles" class="size-4 text-ai" /> Ringkasan AI · {{ $w['bulan'] }}</p>
    <p class="mt-2 text-xs leading-relaxed text-ink/80">{{ $w['ringkasan'] }} {{ $w['saran'][0] ?? '' }}</p>
    <a href="{{ route('waste.index') }}" class="mt-2 inline-block text-xs font-semibold text-brand">Baca ringkasan</a>
</section>