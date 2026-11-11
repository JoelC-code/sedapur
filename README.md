# Habisin (repo: sedapur)

Web app mobile-first untuk mengelola kulkas bersama di rumah atau kos: pantau tanggal kedaluwarsa, habiskan bahan sebelum terbuang, dan lihat berapa uang yang terbuang setiap bulan.

Kelompok 7 · Cloud Computing — Alvaro Grevi Kumesan, Hans Vere Liem, Joel Christopher Gerald Tjokrowiharjo.

## Status

- [x] **Frontend** (Laravel Blade + Tailwind CSS v4) sesuai desain Figma: versi HP (bottom nav) dan desktop ≥ 1024 px (sidebar). Semua data masih contoh dari `app/Support/DemoData.php`.
- [ ] Supabase (Auth, PostgreSQL + RLS, Storage, Realtime, Edge Functions + pg_cron)
- [ ] Gemini API (Gemini 3.1 Flash-Lite) untuk scan struk, scan kulkas, resep, menu, ringkasan
- [x] FatSecret Platform API + tabel TKPI untuk data gizi (tinggal data TKPI yang kurang)
- [ ] CloudPanel + GitHub Actions (deploy otomatis)

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build   # atau: npm run dev
```

Dengan Laravel Herd, buka `http://sedapur.test`. Tanpa Herd: `composer run dev` lalu buka `http://localhost:8000`.

## Halaman

| URL | Layar |
| --- | --- |
| `/masuk`, `/daftar`, `/rumah/mulai` | Masuk, daftar, buat / gabung rumah |
| `/` | Beranda |
| `/kulkas`, `/kulkas/{id}` | Kulkas + detail bahan (panel kanan di desktop) |
| `/scan`, `/scan/gagal`, `/scan/hasil`, `/bahan/manual` | Scan diproses, scan gagal, cek hasil scan, input manual |
| `/menu`, `/resep`, `/resep/{id}` | Menu mingguan, daftar resep, detail resep + catat porsi |
| `/belanja` | Daftar belanja bersama |
| `/gizi` | Gizi & kalori (konsumsi saya / isi kulkas) |
| `/food-waste` | Analitik food waste |
| `/riwayat` | Riwayat aktivitas |
| `/rumah` | Rumah & akun |
| `/pratinjau/email-pengingat` | Pratinjau email pengingat harian |

## Struktur

- `app/Support/DemoData.php` — data contoh dengan bentuk mengikuti ERD (Food_Items, Recipes, Meal_Plans, dst.). Nanti diganti query Supabase.
- `app/Http/Controllers/PageController.php` — satu controller untuk semua halaman tahap frontend.
- `resources/views/components/layouts/` — layout `app` (sidebar + bottom nav) dan `guest` (halaman masuk).
- `resources/css/app.css` — token warna dan komponen (tombol, input, chip, badge) dari UI kit.

## Tes

```bash
php artisan test
```
