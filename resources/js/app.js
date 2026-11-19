import "./bootstrap";

// Buka/tutup <dialog> lewat atribut data-open="id" dan data-close.
document.addEventListener("click", (event) => {
    const opener = event.target.closest("[data-open]");
    if (opener) {
        document.getElementById(opener.dataset.open)?.showModal();
        return;
    }

    const closer = event.target.closest("[data-close]");
    if (closer) {
        closer.closest("dialog")?.close();
        return;
    }

    // Klik di luar isi dialog (backdrop) menutup dialog.
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
});

// Setelah foto struk dipilih dari kamera, unggah lalu lanjut ke layar "scan diproses".
document.addEventListener('change', async (event) => {
    const input = event.target;

    if (!input.matches('input[data-scan]') || !input.files?.length) {
        return;
    }

    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!csrfToken) {
        alert('CSRF token tidak ditemukan.');
        return;
    }

    const formData = new FormData();
    formData.append('receipt', input.files[0]);

    try {
        input.disabled = true;

        const response = await fetch(input.dataset.scan, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (!response.ok) {
            throw new Error(data.message || 'Gagal mengunggah struk.');
        }

    } catch (error) {
        console.error('[Receipt] Upload error:', error);

        alert(error.message || 'Terjadi kesalahan saat mengunggah struk.');

        input.disabled = false;
        input.value = '';
    }
});

// Kotak kode undangan: pindah otomatis ke kotak berikutnya; kode yang di-paste dibagi ke semua kotak.
document.querySelectorAll("[data-code-input]").forEach((group) => {
    const boxes = [...group.querySelectorAll("input")];
    boxes.forEach((box, i) => {
        box.addEventListener("paste", (e) => {
            const chars = (e.clipboardData?.getData("text") ?? "")
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, "");
            if (chars.length < 2) return;
            e.preventDefault();
            boxes.slice(i).forEach((target, j) => {
                target.value = chars[j] ?? "";
            });
            boxes[Math.min(i + chars.length, boxes.length) - 1].focus();
        });
        box.addEventListener("input", () => {
            box.value = box.value.toUpperCase().slice(-1);
            if (box.value && boxes[i + 1]) boxes[i + 1].focus();
        });
        box.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !box.value && boxes[i - 1])
                boxes[i - 1].focus();
        });
    });
});

// Pilihan porsi & tab sederhana: tombol dalam [data-choice] saling eksklusif.
document.addEventListener("click", (event) => {
    const option = event.target.closest("[data-choice] > button");
    if (!option) return;
    const group = option.parentElement;
    group
        .querySelectorAll(":scope > button")
        .forEach((b) => b.classList.remove("is-active"));
    option.classList.add("is-active");
});

// Pesan singkat di [data-copy-status] terdekat (mis. "Kode tersalin"), hilang setelah 2,5 detik.
function showStatus(origin, message) {
    const status =
        origin
            .closest("section, form, div")
            ?.querySelector("[data-copy-status]") ??
        document.querySelector("[data-copy-status]");
    if (!status) return;
    status.textContent = message;
    clearTimeout(status.hideTimer);
    status.hideTimer = setTimeout(() => {
        status.textContent = "";
    }, 2500);
}

// Salin teks. navigator.clipboard hanya ada di HTTPS/localhost; selain itu pakai cara lama (textarea + execCommand).
async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const area = Object.assign(document.createElement("textarea"), {
        value: text,
        readOnly: true,
    });
    area.style.position = "fixed";
    area.style.opacity = "0";
    document.body.append(area);
    area.select();
    const copied = document.execCommand("copy");
    area.remove();
    if (!copied) throw new Error("copy failed");
}

// Salin kode undangan.
document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-copy]");
    if (!button) return;
    try {
        await copyText(button.dataset.copy);
        showStatus(button, "Kode tersalin");
    } catch {
        showStatus(button, "Gagal menyalin, salin kodenya secara manual.");
    }
});

// Bagikan undangan: di HP pakai menu share bawaan; di laptop buka WhatsApp Web.
document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-share-text]");
    if (!button) return;
    const text = button.dataset.shareText;
    const url = button.dataset.shareUrl;
    const isTouch = window.matchMedia("(pointer: coarse)").matches;
    if (isTouch && navigator.share) {
        try {
            await navigator.share({ text, url });
        } catch {
            // Dibatalkan oleh user.
        }
        return;
    }
    window.open(
        `https://wa.me/?text=${encodeURIComponent(`${text}\n${url}`)}`,
        "_blank",
        "noopener",
    );
});

// Form dengan [data-confirm] minta konfirmasi dulu (mis. mengeluarkan anggota).
document.addEventListener("submit", (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

// Form tambah bahan [data-shelf-life]: tanggal kedaluwarsa mengikuti perkiraan umur simpan kategori
// (data-shelf-days) dihitung dari tanggal beli, sampai user mengubah tanggalnya sendiri.
document.addEventListener("change", (event) => {
    const form = event.target.closest("form[data-shelf-life]");
    if (!form) return;
    const expires = form.elements.expires_at;
    if (event.target === expires) {
        expires.dataset.touched = "1";
        return;
    }
    if (expires.dataset.touched) return;
    const category = form.querySelector("input[name=category]:checked");
    if (!category) return;
    const start = form.elements.purchased_at.value
        ? new Date(`${form.elements.purchased_at.value}T00:00:00`)
        : new Date();
    start.setDate(start.getDate() + Number(category.dataset.shelfDays));
    const pad = (n) => String(n).padStart(2, "0");
    expires.value = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
    const hint = form.querySelector("[data-shelf-hint]");
    if (hint) {
        hint.textContent = `Tanggal kedaluwarsa diisi perkiraan sesuai kategori (${category.parentElement.textContent.trim()} ±${category.dataset.shelfDays} hari). Ubah bila kemasan menulis tanggal lain.`;
    }
});
