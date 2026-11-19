// Halaman "Cek hasil scan" (form[data-receipt-review]): total per baris, total belanja,
// jumlah bahan yang disimpan, peringatan angka tidak wajar, dan tanggal kedaluwarsa otomatis.
// Harga satuan dipakai apa adanya: normalisasi Rupiah sudah dilakukan di server.

const SUSPICIOUS_QUANTITY = 100;
const SUSPICIOUS_TOTAL = 1_000_000;

const toNumber = (input) => {
    const value = parseFloat(String(input?.value ?? "").replace(",", "."));
    return Number.isFinite(value) ? value : 0;
};

const rupiah = (value) =>
    `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(value)}`;

function initReceiptReview() {
    const form = document.querySelector("form[data-receipt-review]");
    if (!form) return;

    const shelfDays = JSON.parse(form.dataset.shelfDays || "{}");
    const items = [...form.querySelectorAll("[data-receipt-item]")];

    const refresh = () => {
        let sum = 0;
        let count = 0;

        for (const item of items) {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (!checkbox || checkbox.disabled) continue; // bukan bahan makanan

            const quantity = toNumber(item.querySelector("[data-receipt-qty]"));
            const total = Math.round(quantity * toNumber(item.querySelector("[data-receipt-price]")));

            item.querySelector("[data-receipt-total]").textContent = rupiah(total);
            item.querySelector("[data-receipt-warning]")
                ?.classList.toggle("hidden", !(quantity > SUSPICIOUS_QUANTITY || total > SUSPICIOUS_TOTAL));
            item.classList.toggle("opacity-60", !checkbox.checked);
            // Baris yang tidak dicentang tidak disimpan, jadi isiannya tidak wajib.
            item.querySelectorAll("[data-receipt-required]").forEach((input) => {
                input.required = checkbox.checked;
            });

            if (checkbox.checked) {
                sum += total;
                count += 1;
            }
        }

        form.querySelector("[data-receipt-sum]").textContent = rupiah(sum);
        form.querySelector("[data-receipt-count]").textContent = count;
        form.querySelector("[data-receipt-submit]").disabled = count === 0;
    };

    // Kedaluwarsa = tgl beli + umur simpan kategori, sampai user mengubah tanggalnya sendiri.
    const updateExpiry = (item) => {
        const expiry = item.querySelector("[data-receipt-expiry]");
        if (!expiry || expiry.dataset.touched) return;

        const purchased = item.querySelector("[data-receipt-purchased]")?.value;
        const start = purchased ? new Date(`${purchased}T00:00:00`) : new Date();
        if (Number.isNaN(start.getTime())) return;

        const category = item.querySelector("[data-receipt-category]")?.value;
        start.setDate(start.getDate() + Number(shelfDays[category] ?? 7));
        const pad = (n) => String(n).padStart(2, "0");
        expiry.value = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
    };

    form.addEventListener("input", refresh);
    form.addEventListener("change", (event) => {
        const item = event.target.closest("[data-receipt-item]");
        if (item && event.target.matches("[data-receipt-expiry]")) {
            event.target.dataset.touched = "1";
        } else if (item && event.target.matches("[data-receipt-category], [data-receipt-purchased]")) {
            updateExpiry(item);
        }
        refresh();
    });

    refresh();
}

initReceiptReview();
