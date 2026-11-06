import './bootstrap';

// Buka/tutup <dialog> lewat atribut data-open="id" dan data-close.
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open]');
    if (opener) {
        document.getElementById(opener.dataset.open)?.showModal();
        return;
    }

    const closer = event.target.closest('[data-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }

    // Klik di luar isi dialog (backdrop) menutup dialog.
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
});

// Setelah foto dipilih dari kamera, lanjut ke layar "scan diproses".
document.addEventListener('change', (event) => {
    const input = event.target;
    if (input.matches('input[data-scan]') && input.files?.length) {
        window.location.href = input.dataset.scan;
    }
});

// Kotak kode undangan: pindah otomatis ke kotak berikutnya.
document.querySelectorAll('[data-code-input]').forEach((group) => {
    const boxes = [...group.querySelectorAll('input')];
    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.toUpperCase().slice(-1);
            if (box.value && boxes[i + 1]) boxes[i + 1].focus();
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && boxes[i - 1]) boxes[i - 1].focus();
        });
    });
});

// Centang daftar belanja (sementara di sisi klien; nanti disinkronkan lewat Supabase Realtime).
document.addEventListener('change', (event) => {
    const box = event.target;
    if (box.matches('input[data-shopping]')) {
        box.closest('li')?.classList.toggle('opacity-50', box.checked);
        box.closest('li')?.querySelector('[data-name]')?.classList.toggle('line-through', box.checked);
    }
});

// Pilihan porsi & tab sederhana: tombol dalam [data-choice] saling eksklusif.
document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-choice] > button');
    if (!option) return;
    const group = option.parentElement;
    group.querySelectorAll(':scope > button').forEach((b) => b.classList.remove('is-active'));
    option.classList.add('is-active');
});

// Salin kode undangan.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        button.title = 'Tersalin';
    } catch {
        // Clipboard tidak tersedia; abaikan.
    }
});
