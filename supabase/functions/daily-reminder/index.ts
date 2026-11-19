/**
 * Pengingat email Sedapur, dipanggil pg_cron lewat pg_net (lihat migration add_expiry_reminder_schedule).
 *
 * type "daily"  (Senin-Sabtu): bahan yang kedaluwarsa dalam 2 hari, plus ajakan mencatat bila user
 *                              2 hari tidak membuka aplikasi. Tidak ada yang perlu dikabarkan = tidak dikirim.
 * type "weekly" (Minggu):      rangkuman 7 hari: berapa bahan dimasak, dibuang (dan nilainya dalam Rp),
 *                              serta bahan yang kedaluwarsa minggu depan.
 *
 * Membaca data semua rumah dengan secret key (melewati RLS), jadi hanya boleh dipanggil oleh cron:
 * request wajib membawa header x-cron-secret yang sama dengan secret CRON_SECRET.
 *
 * Secret yang dibutuhkan (supabase secrets set ...):
 *   SB_SECRET_KEY  secret key proyek (sb_secret_...)
 *   CRON_SECRET    string acak, sama dengan isi Vault "sedapur_cron_secret"
 *   SMTP_USER      alamat Gmail pengirim
 *   SMTP_PASS      app password Gmail (bukan password akun)
 *   APP_URL        alamat aplikasi, mis. https://sedapur.jchristopher.my.id
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.4";
import nodemailer from "npm:nodemailer@6.9.16";

type Kind = "daily" | "weekly";

type Item = { household_id: number; name: string; quantity: number; unit: string; expires_at: string };

type Changed = { household_id: number; status: string; price: number | null; quantity: number; initial_quantity: number };

type Member = {
    household_id: number;
    user_id: string;
    households: { name: string } | null;
    profiles: { full_name: string; last_seen_at: string | null } | null;
};

const DAY_MS = 24 * 60 * 60 * 1000;
const INACTIVE_DAYS = 2;

function secret(name: string): string {
    const value = Deno.env.get(name);
    if (!value) throw new Error(`Secret ${name} belum diatur.`);
    return value;
}

/** Tanggal hari ini di WIB (UTC+7) dalam format YYYY-MM-DD, digeser $days hari. */
function wibDate(days = 0): string {
    return new Date(Date.now() + 7 * 60 * 60 * 1000 + days * DAY_MS).toISOString().slice(0, 10);
}

function daysUntil(date: string): number {
    return Math.round((Date.parse(date) - Date.parse(wibDate())) / DAY_MS);
}

function escapeHtml(text: string): string {
    return text.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}

const rupiah = (value: number) => `Rp ${Math.round(value).toLocaleString("id-ID")}`;
const shortDate = (date: string) =>
    new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", timeZone: "UTC" }).format(new Date(`${date}T00:00:00Z`));

function expiryText(date: string): string {
    const days = daysUntil(date);
    if (days < 0) return `lewat ${-days} hari`;
    if (days === 0) return "hari ini";
    if (days === 1) return "besok";
    return `${days} hari lagi`;
}

function itemRows(items: Item[]): string {
    return items.map((item) => `
        <tr>
            <td style="padding:8px 0;font-weight:700">${escapeHtml(item.name)}</td>
            <td style="padding:8px 0;color:#6b6b5e">${Number(item.quantity).toLocaleString("id-ID")} ${escapeHtml(item.unit)}</td>
            <td style="padding:8px 0;text-align:right">${shortDate(item.expires_at)} · ${expiryText(item.expires_at)}</td>
        </tr>`).join("");
}

function layout(title: string, body: string, appUrl: string): string {
    return `<!doctype html><html lang="id"><body style="margin:0;background:#f6f3ea;font-family:Arial,sans-serif;color:#22251c">
        <div style="max-width:560px;margin:0 auto;padding:24px">
            <p style="font-weight:800;color:#4b5a2a;margin:0 0 16px">Sedapur</p>
            <div style="background:#fff;border-radius:16px;padding:24px">
                <h1 style="font-size:20px;margin:0 0 12px">${title}</h1>
                ${body}
                <p style="margin:24px 0 0"><a href="${appUrl}" style="background:#e8603c;color:#fff;padding:12px 18px;border-radius:999px;text-decoration:none;font-weight:700">Buka Sedapur</a></p>
            </div>
            <p style="font-size:12px;color:#6b6b5e">Kamu menerima email ini karena tergabung di rumah ini di Sedapur.</p>
        </div></body></html>`;
}

function dailyEmail(name: string, home: string, expiring: Item[], inactive: boolean, appUrl: string) {
    const parts: string[] = [`<p>Halo ${escapeHtml(name)},</p>`];
    if (expiring.length > 0) {
        parts.push(`<p>Bahan di kulkas <b>${escapeHtml(home)}</b> yang perlu segera dipakai:</p>
            <table style="width:100%;border-collapse:collapse;font-size:14px">${itemRows(expiring)}</table>`);
    }
    if (inactive) {
        parts.push(`<p>Sudah ${INACTIVE_DAYS} hari kamu belum membuka Sedapur. Ada belanjaan baru atau bahan yang sudah terpakai?
            Scan struknya atau tekan "Pakai" supaya isi kulkas tetap akurat.</p>`);
    }
    const subject = expiring.length > 0
        ? `${expiring.length} bahan di ${home} segera kedaluwarsa`
        : `Ada yang belum dicatat di ${home}?`;
    return { subject, html: layout(escapeHtml(subject), parts.join(""), appUrl) };
}

function weeklyEmail(name: string, home: string, expiring: Item[], changed: Changed[], appUrl: string) {
    const cooked = changed.filter((c) => c.status === "dimasak").length;
    const wasted = changed.filter((c) => c.status === "dibuang");
    // Nilai terbuang dihitung dari sisa saat dibuang, bukan harga penuh (¼ kotak susu = ¼ harga).
    const wastedValue = wasted.reduce((sum, c) => sum + (c.price ?? 0) * (c.quantity / (c.initial_quantity || c.quantity)), 0);
    const body = `<p>Halo ${escapeHtml(name)}, ini rangkuman 7 hari terakhir di <b>${escapeHtml(home)}</b>:</p>
        <ul style="padding-left:18px;line-height:1.7">
            <li><b>${cooked}</b> bahan habis dimasak</li>
            <li><b>${wasted.length}</b> bahan dibuang${wastedValue > 0 ? `, senilai <b>${rupiah(wastedValue)}</b>` : ""}</li>
        </ul>
        ${expiring.length > 0
            ? `<p>Kedaluwarsa dalam 7 hari ke depan:</p><table style="width:100%;border-collapse:collapse;font-size:14px">${itemRows(expiring)}</table>`
            : "<p>Tidak ada bahan yang kedaluwarsa minggu depan.</p>"}`;
    const subject = `Rangkuman minggu ini di ${home}`;
    return { subject, html: layout(escapeHtml(subject), body, appUrl) };
}

function groupBy<T extends { household_id: number }>(rows: T[]): Map<number, T[]> {
    const groups = new Map<number, T[]>();
    for (const row of rows) groups.set(row.household_id, [...(groups.get(row.household_id) ?? []), row]);
    return groups;
}

Deno.serve(async (request) => {
    if (request.method !== "POST" || request.headers.get("x-cron-secret") !== secret("CRON_SECRET")) {
        return new Response("Forbidden", { status: 403 });
    }

    const { type = "daily" } = await request.json().catch(() => ({}));
    if (type !== "daily" && type !== "weekly") {
        return Response.json({ error: "type harus daily atau weekly" }, { status: 400 });
    }
    const kind = type as Kind;
    const appUrl = Deno.env.get("APP_URL") ?? "https://sedapur.jchristopher.my.id";

    const db = createClient(secret("SUPABASE_URL"), secret("SB_SECRET_KEY"), { auth: { persistSession: false } });

    const [items, members, changed, users] = await Promise.all([
        db.from("food_items").select("household_id,name,quantity,unit,expires_at")
            .eq("status", "tersedia").not("expires_at", "is", null)
            .lte("expires_at", wibDate(kind === "weekly" ? 7 : 2)).order("expires_at"),
        db.from("household_members").select("household_id,user_id,households(name),profiles(full_name,last_seen_at)"),
        kind === "weekly"
            ? db.from("food_items").select("household_id,status,price,quantity,initial_quantity")
                .in("status", ["dimasak", "dibuang"]).gte("status_changed_at", new Date(Date.now() - 7 * DAY_MS).toISOString())
            : Promise.resolve({ data: [] as Changed[], error: null }),
        db.auth.admin.listUsers({ perPage: 1000 }),
    ]);

    for (const result of [items, members, changed, users]) {
        if (result.error) {
            console.error(result.error);
            return Response.json({ error: "Gagal membaca data" }, { status: 500 });
        }
    }

    const emails = new Map(users.data.users.map((u) => [u.id, u.email]));
    const expiringByHome = groupBy((items.data ?? []) as Item[]);
    const changedByHome = groupBy((changed.data ?? []) as Changed[]);

    const transport = nodemailer.createTransport({
        host: "smtp.gmail.com",
        port: 465,
        secure: true,
        auth: { user: secret("SMTP_USER"), pass: secret("SMTP_PASS") },
    });

    let sent = 0, skipped = 0, failed = 0;

    // Relasi many-to-one (households, profiles) dikembalikan PostgREST sebagai objek tunggal.
    for (const member of (members.data ?? []) as unknown as Member[]) {
        const to = emails.get(member.user_id);
        const name = member.profiles?.full_name ?? "Anggota";
        const home = member.households?.name ?? "rumahmu";
        const expiring = expiringByHome.get(member.household_id) ?? [];
        const lastSeen = member.profiles?.last_seen_at ? Date.parse(member.profiles.last_seen_at) : 0;
        const inactive = Date.now() - lastSeen > INACTIVE_DAYS * DAY_MS;
        const homeChanged = changedByHome.get(member.household_id) ?? [];

        const nothingToSay = kind === "daily"
            ? expiring.length === 0 && !inactive
            : expiring.length === 0 && homeChanged.length === 0;

        if (!to || nothingToSay) {
            skipped++;
            continue;
        }

        const email = kind === "daily"
            ? dailyEmail(name, home, expiring, inactive, appUrl)
            : weeklyEmail(name, home, expiring, homeChanged, appUrl);

        try {
            await transport.sendMail({ from: `Sedapur <${secret("SMTP_USER")}>`, to, subject: email.subject, html: email.html });
            sent++;
        } catch (error) {
            console.error(`Gagal mengirim ke ${member.user_id}:`, error);
            failed++;
        }
    }

    return Response.json({ type: kind, sent, skipped, failed });
});
