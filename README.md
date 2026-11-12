# Sedapur

Web app mobile-first untuk mengelola kulkas bersama di rumah atau kos: pantau tanggal kedaluwarsa, habiskan bahan sebelum terbuang, dan lihat berapa uang yang terbuang setiap bulan.

Kelompok 7 · Cloud Computing — Alvaro Grevi Kumesan, Hans Vere Liem, Joel Christopher Gerald Tjokrowiharjo.

## Progres fitur

Semua layar sudah dibuat sesuai desain Figma (versi HP dan desktop). Layar masih memakai data contoh dari `app/Support/DemoData.php` sampai backend tiap fitur tersambung.

| # | Fitur | Tampilan (UI) | Backend / data |
| --- | --- | --- | --- |
| 1 | Kulkas bersama + kode undangan | Selesai | Sebagian: tabel `households`, `household_members`, `food_items` di Supabase dengan RLS dan kode undangan otomatis. Realtime belum. |
| 2 | Scan struk (Gemini Vision) | Selesai | Belum, menunggu Gemini API |
| 3 | Scan isi kulkas (Gemini Vision) | Selesai | Belum, menunggu Gemini API |
| 4 | Input manual + autocomplete bahan | Selesai | Sebagian: API pencarian gizi `/api/nutrition/search` (FatSecret + TKPI). Belum disambungkan ke form. |
| 5 | Pemantauan kedaluwarsa + pengingat email | Selesai (termasuk template email) | Belum (Edge Function + pg_cron) |
| 6 | Resep + rencana menu mingguan AI | Selesai | Belum, menunggu Gemini API |
| 7 | Daftar belanja bersama | Selesai | Belum |
| 8 | Kalori dan gizi | Selesai | Sebagian: sumber data gizi FatSecret + TKPI sudah ada. Perhitungan total dan catatan konsumsi belum. |
| 9 | Riwayat aktivitas | Selesai | Belum |
| 10 | Analitik food waste + ringkasan AI | Selesai | Belum |

## Teknologi

- [x] Frontend Laravel Blade + Tailwind CSS v4, PWA manifest
- [x] Supabase PostgreSQL + Row Level Security
- [ ] Supabase Auth
- [ ] Supabase Storage
- [ ] Supabase Realtime
- [ ] Supabase Edge Functions + pg_cron
- [ ] Gemini API (Gemini 3.1 Flash-Lite) untuk scan struk, scan kulkas, resep, menu, ringkasan
- [x] FatSecret Platform API + tabel TKPI untuk data gizi
- [ ] CloudPanel + GitHub Actions (deploy otomatis)

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Dengan Laravel Herd, buka `http://sedapur.test`. 

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
- `app/Http/Controllers/NutritionController.php`, `app/Services/FatSecretService.php`, `app/Services/TkpiService.php` — pencarian data gizi.
- `database/migrations/` — tabel Laravel, `tkpi_items`, tabel rumah dan kulkas, serta RLS untuk Supabase.
- `resources/views/components/layouts/` — layout `app` (sidebar + bottom nav) dan `guest` (halaman masuk).
- `resources/css/app.css` — token warna dan komponen (tombol, input, chip, badge) dari UI kit.

## Tes

```bash
php artisan test
```
