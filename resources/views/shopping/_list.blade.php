{{-- Isi daftar belanja. Dipakai halaman /belanja dan dimuat ulang oleh Realtime lewat /belanja/daftar. --}}
@php [$checked, $unchecked] = $items->partition(fn ($i) => $i['dicentang']); @endphp
@if ($items->isEmpty())
    <div class="card-sand mt-5 py-10 text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-full bg-white text-olive-700"><x-icon name="cart" class="size-5" /></span>
        @if ($loadFailed)
            <p class="mt-2 font-bold">Daftar belanja belum bisa dimuat</p>
            <p class="text-sm text-muted">Koneksi ke server sedang bermasalah. Muat ulang halaman sebentar lagi.</p>
        @else
            <p class="mt-2 font-bold">Daftar belanja masih kosong</p>
            <p class="text-sm text-muted">Tambahkan barang yang perlu dibeli. Semua anggota rumah melihat daftar yang sama.</p>
        @endif
    </div>
@endif

@foreach (['Perlu dibeli' => $unchecked, 'Sudah dibeli' => $checked] as $heading => $group)
    @continue($group->isEmpty())
    <p class="stat-label mt-5 mb-2">{{ $heading }} · {{ $group->count() }}</p>
    <ul class="space-y-2">
        @foreach ($group as $item)
            <li data-item-id="{{ $item['id'] }}" @class(['flex items-center gap-2 rounded-2xl bg-sand py-1.5 pr-2 pl-3.5', 'opacity-60' => $item['dicentang']])>
                <form method="POST" action="{{ route('shopping.update', $item['id']) }}" class="flex">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_checked" value="{{ $item['dicentang'] ? 0 : 1 }}">
                    {{-- Area sentuh 40px, kotak centang 24px --}}
                    <button class="-ml-2 grid size-10 place-items-center" aria-label="{{ $item['dicentang'] ? 'Batalkan centang' : 'Tandai sudah dibeli' }}: {{ $item['nama'] }}">
                        <span @class(['grid size-6 place-items-center rounded-lg border-2 border-olive-700', 'bg-olive-700 text-white' => $item['dicentang'], 'bg-white' => ! $item['dicentang']])>
                            @if ($item['dicentang']) <x-icon name="check" class="size-4" /> @endif
                        </span>
                    </button>
                </form>
                <span @class(['min-w-0 flex-1 truncate text-[15px] font-semibold', 'line-through' => $item['dicentang']])>{{ $item['nama'] }}</span>
                <span class="shrink-0 text-xs text-muted">{{ $item['jumlah'] }} {{ $item['satuan'] }}</span>
                <form method="POST" action="{{ route('shopping.destroy', $item['id']) }}" class="flex">
                    @csrf @method('DELETE')
                    <button class="grid size-9 place-items-center rounded-full text-muted hover:bg-sand-dark hover:text-danger" aria-label="Hapus {{ $item['nama'] }}"><x-icon name="trash" class="size-4" /></button>
                </form>
            </li>
        @endforeach
    </ul>
@endforeach
