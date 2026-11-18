<x-layouts.app title="Scan diproses" active="fridge.index" bare>
    <div
        class="-mx-5 -mt-5 flex min-h-dvh flex-col bg-[#1d2010] px-5 pt-5 pb-8 text-white lg:mx-0 lg:mt-6 lg:min-h-[calc(100dvh-3rem)] lg:rounded-3xl">
        <div class="flex items-center">
            <a href="{{ route('fridge.index') }}" class="grid size-10 place-items-center rounded-full bg-white/10"
                aria-label="Tutup"><x-icon name="x" class="size-4" /></a>
            <p class="flex-1 text-center font-bold">Scan Struk</p>
            <span class="size-10"></span>
        </div>

        <div class="relative mx-auto mt-10 flex w-full max-w-md justify-center">
            <div class="grid h-64 w-64 place-items-center rounded-3xl bg-white/5">
                <div class="flex flex-col items-center gap-3 text-white/70">
                    <x-icon name="receipt-text" class="size-20 text-olive-200" />
                    <p class="text-sm font-medium">Menganalisis struk...</p>
                </div>
            </div>

            <span
                class="absolute inset-x-0 top-1/2 h-[3px] animate-pulse rounded bg-olive-200 shadow-[0_0_18px_4px_rgba(221,235,143,.9)]">
            </span>
        </div>

        <div class="mx-auto mt-auto w-full max-w-md rounded-2xl bg-white/10 p-4">
            <p class="flex items-center gap-2 font-bold"><x-icon name="sparkles" class="size-4 text-olive-200" />
                Membaca Struk</p>
            <p class="mt-1 text-xs text-white/70">Gemini sedang mengambil nama item dan jumlah dari struk,
                kemudian mencocokkan informasi gizinya.</p>
            <ul class="mt-3 space-y-1.5 text-sm">
                <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-olive-200" /> Membaca item
                </li>
                <li class="flex items-center gap-2 text-white/50"><span
                        class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    Mencocokkan data gizi</li>
            </ul>
        </div>

        <a href="{{ route('fridge.index') }}"
            class="btn mx-auto mt-4 w-full max-w-md bg-white/10 text-white hover:bg-white/15">Batalkan</a>
    </div>

    <script>
        const statusUrl = @json(route('scan.status', $scan));

        async function checkStatus() {
            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Could not retrieve scan status.');
                }

                const data = await response.json();

                if (data.status === 'completed') {
                    window.location.href = data.review_url;
                    return;
                }

                if (data.status === 'failed') {
                    window.location.href = data.failed_url;
                    return;
                }

                setTimeout(checkStatus, 2000);

            } catch (error) {
                console.error(error);

                setTimeout(checkStatus, 4000);
            }
        }

        checkStatus();
    </script>
</x-layouts.app>
