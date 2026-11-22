@php use App\Support\DemoData as D; @endphp
<x-layouts.app :title="$selected['nama'] ?? 'Kulkas'" active="fridge.index" :bare="(bool) $selected">
    @if (session('status'))
        <div class="mb-4">@include('partials.status')</div>
    @endif
    <div class="lg:flex lg:gap-6">
        {{-- Daftar bahan (di HP disembunyikan saat detail dibuka) --}}
        <section @class(['min-w-0 flex-1', 'hidden lg:block' => $selected])>
            <x-page-header title="Kulkas" :subtitle="$items->count().' bahan '.$status">
                <x-avatars class="lg:hidden" />
                <form class="relative hidden w-72 lg:block">
                    <x-icon name="search" class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
                    <input name="q" value="{{ request('q') }}" class="input h-11 pl-10" placeholder="Cari bahan">
                </form>
                <button type="button" data-open="add-item" class="btn-primary btn-sm hidden lg:inline-flex"><x-icon name="plus" class="size-4" /> Tambah bahan</button>
            </x-page-header>

            <form class="relative mb-4 lg:hidden">
                <x-icon name="search" class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ request('q') }}" class="input pl-10" placeholder="Cari bahan">
            </form>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="seg lg:w-80">
                    @foreach (['tersedia' => 'Tersedia', 'dimasak' => 'Dimasak', 'dibuang' => 'Dibuang'] as $key => $label)
                        <a href="{{ route('fridge.index', ['status' => $key]) }}" @class(['is-active' => $status === $key])>{{ $label }}</a>
                    @endforeach
                </div>
                <p class="hidden flex-1 text-right text-xs text-muted lg:block">Urut: paling cepat kedaluwarsa ▾</p>
            </div>

            <div class="no-scrollbar -mx-5 mt-3 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:flex-wrap lg:px-0">
                <a href="{{ route('fridge.index') }}" class="{{ $kategori ? 'chip' : 'chip-active' }}">Semua</a>
                @foreach ($categories as $key => $cat)
                    <a href="{{ route('fridge.index', ['kategori' => $key]) }}" class="{{ $kategori === $key ? 'chip-active' : 'chip' }}">{{ $cat['label'] }}</a>
                @endforeach
            </div>

            <p class="mt-4 mb-2 text-xs text-muted lg:hidden">Urut: paling cepat kedaluwarsa</p>

            {{-- Realtime memuat ulang isi elemen ini saat anggota lain mengubah isi kulkas (resources/js/realtime-list.js). --}}
            <div data-realtime-table="food_items"
                data-supabase-url="{{ config('services.supabase.url') }}"
                data-supabase-key="{{ config('services.supabase.publishable_key') }}"
                data-household-id="{{ $householdId }}"
                data-token-url="{{ route('session.token') }}">
            @if ($items->isEmpty())
                <div class="card-sand mt-4 py-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-white text-olive-700"><x-icon name="fridge" class="size-5" /></span>
                    @if ($loadFailed)
                        <p class="mt-2 font-bold">Isi kulkas belum bisa dimuat</p>
                        <p class="text-sm text-muted">Koneksi ke server sedang bermasalah. Muat ulang halaman sebentar lagi.</p>
                    @else
                        <p class="mt-2 font-bold">Belum ada bahan di sini</p>
                        <p class="text-sm text-muted">Bahan yang {{ $status === 'tersedia' ? 'cocok dengan filter' : 'ditandai '.$status }} akan muncul di daftar ini.</p>
                    @endif
                </div>
            @else
                {{-- HP --}}
                <div class="space-y-2 lg:hidden">
                    @foreach ($items as $item)
                        <x-food-row :item="$item" />
                    @endforeach
                </div>

                {{-- Desktop --}}
                <div class="mt-4 hidden overflow-x-auto lg:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] tracking-[.07em] text-muted uppercase">
                                @foreach (['Bahan', 'Kategori', 'Jumlah', 'Berat', 'Harga', 'Ditambah', 'Kedaluwarsa'] as $th)
                                    <th class="px-3 py-2.5 font-bold whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php [$label, $cls] = D::expiryBadge($item['sisa_hari']); @endphp
                                <tr @class(['border-b border-line', 'bg-olive-100' => ($selected['id'] ?? null) === $item['id'], 'hover:bg-sand/60' => ($selected['id'] ?? null) !== $item['id']])>
                                    <td class="px-3 py-2.5 font-bold whitespace-nowrap"><a href="{{ route('fridge.show', $item['id']) }}" class="hover:text-olive-700">{{ $item['nama'] }}</a></td>
                                    <td class="px-3 py-2.5 text-muted">{{ $categories[$item['kategori']]['label'] }}</td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">{{ $item['jumlah'] }} {{ $item['satuan'] }}</td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">{{ $item['berat_gram'] ? D::number($item['berat_gram']).' g' : '—' }}</td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">{{ D::rupiah($item['harga']) }}</td>
                                    <td class="px-3 py-2.5">{{ $item['ditambah_oleh'] }}</td>
                                    <td class="px-3 py-2.5"><span class="days {{ $cls }}">{{ $label }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-muted">Menampilkan {{ $items->count() }} bahan</p>
                </div>
            @endif
            </div>
        </section>

        {{-- Detail bahan: halaman penuh di HP, panel kanan di desktop --}}
        @if ($selected)
            @php
                [$label, $cls] = D::expiryBadge($selected['sisa_hari']);
                $gizi = $selected['gizi'];
            @endphp
            <aside class="lg:-my-0 lg:w-[380px] lg:shrink-0 lg:border-l lg:border-line lg:pt-6 lg:pl-6">
                <div class="mb-4 flex items-center justify-between">
                    <a href="{{ route('fridge.index') }}" class="grid size-10 place-items-center rounded-full bg-sand lg:hidden" aria-label="Kembali"><x-icon name="chevron-left" class="size-4" /></a>
                    <p class="stat-label hidden lg:block">Detail bahan</p>
                    <div class="flex gap-2">
                        @if ($selected['status'] === 'tersedia')
                            <a href="{{ route('fridge.edit', $selected['id']) }}" class="grid size-10 place-items-center rounded-full bg-sand" aria-label="Ubah"><x-icon name="edit" class="size-4" /></a>
                        @endif
                        <a href="{{ route('fridge.index') }}" class="hidden size-10 place-items-center rounded-full bg-sand lg:grid" aria-label="Tutup"><x-icon name="x" class="size-4" /></a>
                    </div>
                </div>

                <div>
                        <h1 class="text-2xl font-extrabold">{{ $selected['nama'] }}</h1>
                        <div class="mt-1 flex gap-1.5">
                            <span class="badge bg-sand text-muted">{{ $categories[$selected['kategori']]['label'] }}</span>
                            <span class="badge {{ $cls }}">{{ $label }}</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ match (true) { $selected['sisa_hari'] === null => 'Tanggal kedaluwarsa belum diisi', $selected['sisa_hari'] < 0 =>'Lewat '.abs($selected['sisa_hari']).' hari', $selected['sisa_hari'] === 0 => 'Kedaluwarsa hari ini', default => $selected['sisa_hari'].' hari lagi' } }}</p>
                </div>

                @if ($selected['status'] === 'tersedia')
                    <div class="card-sand mt-4">
                        <div class="flex items-baseline justify-between">
                            <p class="stat-label">Sisa</p>
                            <p class="text-sm font-bold">{{ $selected['jumlah'] }} {{ $selected['satuan'] }} · {{ $selected['sisa_persen'] }}%</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white" role="progressbar" aria-label="Sisa bahan" aria-valuenow="{{ $selected['sisa_persen'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-olive-700" style="width: {{ $selected['sisa_persen'] }}%"></div>
                        </div>
                        <p class="mt-3 text-xs text-muted">Baru dipakai? Catat sekali tekan:</p>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @foreach ($selected['pakai'] as $option)
                                <form method="POST" action="{{ route('fridge.use', $selected['id']) }}">
                                    @csrf
                                    <button name="amount" value="{{ $option['amount'] }}" class="chip bg-white">Pakai {{ $option['label'] }}</button>
                                </form>
                            @endforeach
                            <form method="POST" action="{{ route('fridge.status', $selected['id']) }}">
                                @csrf @method('PATCH')
                                <button name="status" value="dimasak" class="chip bg-white">Habis</button>
                            </form>
                        </div>
                    </div>
                @endif

                <dl class="card-sand mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                    @foreach ([
                        ['Jumlah', $selected['jumlah'].' '.$selected['satuan'], ''],
                        ['Berat', $selected['berat_gram'] ? D::number($selected['berat_gram']).' g' : '—', ''],
                        ['Harga', D::rupiah($selected['harga']), ''],
                        ['Dibeli', $selected['tgl_beli']?->translatedFormat('j M Y') ?? '—', ''],
                        ['Kedaluwarsa', $selected['tgl_kedaluwarsa']?->translatedFormat('j M Y') ?? '—', 'text-warn'],
                        ['Sumber', $selected['sumber'], ''],
                    ] as [$k, $v, $c])
                        <div>
                            <dt class="stat-label">{{ $k }}</dt>
                            <dd class="font-bold {{ $c }}">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($gizi)
                    @php [$srcLabel, $srcCls] = D::sourceBadge($gizi['data_source']); @endphp
                    <div class="mt-5 flex items-center justify-between">
                        <h2 class="font-bold">Gizi per 100 g</h2>
                        <span class="badge {{ $srcCls }}">{{ $srcLabel }}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-4 gap-2 text-center">
                        @foreach ([['calories', 'kkal', ''], ['protein', 'protein', ' g'], ['carbohydrates', 'karbo', ' g'], ['fat', 'lemak', ' g']] as [$key, $unit, $suffix])
                            <div class="rounded-[14px] bg-sand px-2 py-2.5">
                                <b class="block text-base font-extrabold">{{ str_replace('.', ',', (string) round($gizi[$key], 1)) }}{{ $suffix }}</b>
                                <span class="text-[11px] text-muted">{{ $unit }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-muted">Data gizi untuk "{{ $gizi['name'] }}".</p>
                @endif

                <h2 class="mt-5 mb-2 font-bold">Riwayat</h2>
                <ul class="space-y-2.5 text-sm">
                    <li class="flex items-center gap-2">@include('partials.member-dot', ['name' => $selected['ditambah_oleh']]) <span class="flex-1">{{ $selected['ditambah_oleh'] }} menambahkan dari {{ mb_strtolower($selected['sumber']) }}</span></li>
                </ul>

                @if ($selected['status'] === 'tersedia')
                    <div class="mt-6 grid grid-cols-[auto_1fr] gap-2">
                        <form method="POST" action="{{ route('fridge.status', $selected['id']) }}">
                            @csrf @method('PATCH')
                            <button name="status" value="dibuang" class="btn-danger w-full"><x-icon name="trash" class="size-4" /> Buang</button>
                        </form>
                        <form method="POST" action="{{ route('fridge.status', $selected['id']) }}">
                            @csrf @method('PATCH')
                            <button name="status" value="dimasak" class="btn-primary w-full"><x-icon name="utensils" class="size-4" /> Tandai dimasak</button>
                        </form>
                    </div>
                @endif
            </aside>
        @endif
    </div>
</x-layouts.app>
