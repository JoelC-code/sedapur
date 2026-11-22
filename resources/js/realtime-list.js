// Daftar live (belanja, kulkas): Supabase Realtime (postgres_changes) memberi tahu bahwa tabel
// [data-realtime-table] milik rumah ini berubah, lalu isi elemen itu dimuat ulang dari server
// (HTML Blade, sudah di-escape): dari data-list-url bila ada, kalau tidak dari halaman ini sendiri.
// Browser hanya memegang publishable key dan access token user; RLS menentukan event yang boleh diterima.
import { createClient } from "@supabase/supabase-js";

const REFRESH_BEFORE_SECONDS = 60; // sama dengan REFRESH_MARGIN_SECONDS di middleware supabase.auth
const RELOAD_DEBOUNCE_MS = 300;

const root = document.querySelector("[data-realtime-table]");
const table = root.dataset.realtimeTable;
const status = document.querySelector("[data-realtime-status]");

// Token tidak ditulis di HTML: diambil dari /sesi/token saat connect pertama, lalu diperbarui bila hampir habis.
let token = null;
let expiresAt = 0;
let pendingToken = null;
let ended = false;
let reloadTimer;

const setStatus = (state, text) => {
    if (!status) return;
    status.dataset.state = state;
    status.querySelector("[data-realtime-text]").textContent = text;
};

const disconnected = () => setStatus("off", "Terputus · muat ulang halaman");

const headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };

// Sesi habis (dialihkan ke login atau 401): berhenti mendengarkan, jangan coba lagi.
const sessionEnded = (response) => response.redirected || response.status === 401;

async function fetchToken() {
    const response = await fetch(root.dataset.tokenUrl, { credentials: "same-origin", headers });
    if (sessionEnded(response) || !response.ok) {
        stop();
        return null;
    }
    ({ access_token: token, expires_at: expiresAt } = await response.json());
    return token;
}

// Dipanggil supabase-js saat connect dan tiap heartbeat (25 detik), bisa bersamaan: satu fetch saja.
async function accessToken() {
    if (ended) return null;
    if (expiresAt - Date.now() / 1000 > REFRESH_BEFORE_SECONDS) return token;
    pendingToken ??= fetchToken().finally(() => {
        pendingToken = null;
    });
    return pendingToken;
}

async function reloadList() {
    const url = root.dataset.listUrl ?? location.href;
    const response = await fetch(url, { credentials: "same-origin", headers }).catch(() => null);
    if (!response) return;
    if (sessionEnded(response)) {
        stop();
        return;
    }
    if (!response.ok) return;
    const html = await response.text();
    root.innerHTML = root.dataset.listUrl
        ? html
        : (new DOMParser().parseFromString(html, "text/html").querySelector("[data-realtime-table]")?.innerHTML ?? root.innerHTML);
}

const scheduleReload = () => {
    if (ended) return;
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(reloadList, RELOAD_DEBOUNCE_MS);
};

const supabase = createClient(root.dataset.supabaseUrl, root.dataset.supabaseKey, { accessToken });
const householdId = Number(root.dataset.householdId);

const channel = supabase
    .channel(`${table}-${householdId}`, { config: { postgres_changes_options: { wait: true } } })
    .on(
        "postgres_changes",
        { event: "*", schema: "public", table, filter: `household_id=eq.${householdId}` },
        scheduleReload,
    )
    // DELETE tidak bisa disaring per rumah dan hanya membawa old.id: muat ulang hanya bila item itu ada di sini.
    .on("postgres_changes", { event: "DELETE", schema: "public", table }, (payload) => {
        const id = Number(payload.old?.id);
        if (id && root.querySelector(`[data-item-id="${id}"]`)) scheduleReload();
    })
    .subscribe((state) => {
        if (state === "SUBSCRIBED") {
            setStatus("ok", "Tersambung · perubahan muncul otomatis");
            scheduleReload(); // tangkap perubahan yang terlewat sebelum/selama terputus
        } else if (!ended) {
            disconnected();
        }
    });

function stop() {
    if (ended) return;
    ended = true;
    clearTimeout(reloadTimer);
    supabase.removeChannel(channel);
    disconnected();
}

document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") scheduleReload();
});

// Halaman ditinggal (termasuk masuk bfcache): lepas channel. Bila dipulihkan dari bfcache, muat ulang
// supaya daftar dan langganan Realtime segar lagi.
window.addEventListener("pagehide", stop);
window.addEventListener("pageshow", (event) => {
    if (event.persisted) location.reload();
});
