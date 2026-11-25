@php
    $isFridge = $scan instanceof \App\Models\FridgeScan;

    $ui = $isFridge
        ? [
            'title' => 'Scan Kulkas',
            'heading' => 'Isi kulkas tidak terbaca',
            'tips' => [
                'Foto dari jarak dekat dan pastikan bahan terlihat jelas',
                'Gunakan cahaya yang cukup, hindari bayangan dan pantulan',
                'Jangan menumpuk bahan sampai saling menutupi',
            ],
        ]
        : [
            'title' => 'Scan Struk',
            'heading' => 'Struk tidak terbaca',
            'tips' => [
                'Ratakan struk dan foto dari atas, tidak miring',
                'Pastikan seluruh struk masuk ke dalam foto',
                'Gunakan cahaya yang cukup agar tulisan tajam',
            ],
        ];
@endphp
<x-layouts.app title="Scan gagal" active="fridge.index" bare>
    <div
        class="-mx-5 -mt-5 flex min-h-dvh flex-col bg-[#1d2010] px-5 pt-5 pb-8 text-white lg:mx-0 lg:mt-6 lg:min-h-[calc(100dvh-3rem)] lg:rounded-3xl">
        <div class="flex items-center">
            <a href="{{ route('fridge.index') }}" class="grid size-10 place-items-center rounded-full bg-white/10"
                aria-label="Tutup"><x-icon name="x" class="size-4" /></a>
            <p class="flex-1 text-center font-bold">{{ $ui['title'] }}</p>
            <span class="size-10"></span>
        </div>

        <div class="mx-auto mt-16 flex w-full max-w-md flex-col items-center text-center">
            <span class="grid size-20 place-items-center rounded-full bg-white/10">
                <x-icon name="x" class="size-8 text-olive-200" />
            </span>
            <h1 class="mt-5 text-lg font-bold">{{ $ui['heading'] }}</h1>
            <p class="mt-1 text-sm text-white/70">Maaf, kami tidak bisa memproses fotonya. Silakan coba lagi.</p>
        </div>

        <div class="mx-auto mt-8 w-full max-w-md rounded-2xl bg-white/10 p-4">
            <p class="font-bold">Tips agar berhasil</p>
            <ul class="mt-2 space-y-1.5 text-sm text-white/80">
                @foreach ($ui['tips'] as $tip)
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-olive-200" /> {{ $tip }}</li>
                @endforeach
            </ul>
        </div>

        <div class="mx-auto mt-auto w-full max-w-md space-y-2 pt-6">
            <label class="btn w-full cursor-pointer bg-white/10 text-white hover:bg-white/15">
                Input lagi
                <input type="file" accept="image/*" capture="environment" class="sr-only"
                    name="{{ $isFridge ? 'photo' : 'receipt' }}"
                    data-scan="{{ $isFridge ? route('fridge-scan.upload') : route('scan.upload') }}"
                    @if ($isFridge) data-scan-field="photo" data-scan-label="foto kulkas" @endif>
            </label>
            <a href="{{ route('fridge.index') }}" class="btn-primary w-full">Kembali</a>
        </div>
    </div>
</x-layouts.app>