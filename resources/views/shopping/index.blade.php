@php [$checked, $unchecked] = $items->partition(fn ($i) => $i['dicentang']); @endphp
<x-layouts.app title="Daftar belanja" active="shopping.index">
    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif

    <x-page-header title="Daftar belanja" subtitle="Dipakai bersama semua anggota rumah">
        <x-avatars />
    </x-page-header>

    <div class="lg:grid lg:grid-cols-[1fr_320px] lg:gap-8">
        <div>
            <form method="POST" action="{{ route('shopping.store') }}" class="space-y-2">
                @csrf
                <div>
                    <label class="sr-only" for="name">Nama barang</label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="100" required class="input" placeholder="Tambah item, mis. bawang merah">
                    @error('name') <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="quantity">Jumlah</label>
                        <input id="quantity" name="quantity" type="number" inputmode="decimal" step="any" min="0.01" value="{{ old('quantity', 1) }}" required class="input" placeholder="Jumlah">
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="unit">Satuan</label>
                        <select id="unit" name="unit" class="input">
                            @foreach ($units as $unit)
                                <option @selected(old('unit', 'buah') === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="grid size-[52px] shrink-0 place-items-center rounded-full bg-brand text-white hover:bg-brand-dark" aria-label="Tambah ke daftar belanja"><x-icon name="plus" /></button>
                </div>
                @error('quantity') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                @error('unit') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
            </form>

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
                        <li @class(['flex items-center gap-2 rounded-2xl bg-sand py-1.5 pr-2 pl-3.5', 'opacity-60' => $item['dicentang']])>
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
        </div>

        <aside class="mt-6 space-y-3 lg:mt-0">
            <div class="card-sand">
                <p class="flex items-center gap-2 text-sm font-bold"><x-icon name="users" class="size-4 text-olive-700" /> Dipakai bersama</p>
                <p class="mt-1 text-xs text-muted">Semua anggota rumah melihat daftar yang sama. Muat ulang halaman untuk melihat perubahan terbaru.</p>
            </div>
            <div class="rounded-[20px] bg-olive-100 p-4">
                <p class="flex items-center gap-2 text-sm font-bold">Bahan resep otomatis masuk <span class="badge bg-sand-dark text-muted">Segera hadir</span></p>
                <p class="mt-1 text-xs text-muted">Bahan yang belum ada di kulkas dari resep di menu minggu ini ditambahkan ke daftar ini.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
