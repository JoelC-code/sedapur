# Sedapur

A mobile-first web app (installable PWA) for a fridge shared by a household or boarding house (kos): track expiry dates, use ingredients before they go to waste, and see how much money is thrown away each month.

Group 7\
Cloud Computing\
Alvaro Grevi Kumesan\
Hans Vere Liem\
Joel Christopher Gerald Tjokrowiharjo

## Feature progress

Every screen is built from the Figma design (phone and desktop layouts) with the warm terracotta and emerald palette. Core features — authentication, household sharing, fridge CRUD, receipt scanning, shared shopping list, Realtime sync, activity history, food waste analytics, and scheduled email reminders — are connected to Supabase. Only recipes, the weekly AI meal plan, the `/gizi` summary/intake tracker, and the AI food waste summary text still use sample data from `app/Support/DemoData.php`. Ingredients are shown as text with a category label (no emoji), because Unicode food emoji only cover about 59% of the 1,146 TKPI ingredients.

| # | Feature | UI | Backend / data |
| --- | --- | --- | --- |
| 1 | Sign up, sign in, sign out | Done | Done: email + password through Supabase Auth, automatic session refresh, route protection, and `/sesi/token` for browser Realtime. Google sign-in was removed by agreement with the lecturer. |
| 2 | Shared fridge + invite code | Done | Done: `households`, `household_members`, `profiles`, and `food_items` with Row Level Security. Create/join via invite code, member management (kick/leave), full fridge CRUD (add, edit, partial use, cooked/discarded), and live Supabase Realtime sync. |
| 3 | Receipt scan (Gemini Vision) | Done | Done: uploads receipt photos to private Supabase Storage bucket (`receipts`), extracts items with a queued Gemini job (`ProcessReceiptScan`), editable review screen, and saves selected items to `food_items`. |
| 4 | Fridge photo scan (Gemini Vision) | Done | In progress on branch `Fridge-scan` (not yet merged to `main`). |
| 5 | Manual input + ingredient lookup | Done | Done (manual input): saves to `food_items` with category shelf-life defaults; item detail displays TKPI nutrition per 100 g. Nutrition search API `/api/nutrition/search` (FatSecret, then TKPI) is ready; form autocomplete not yet wired. |
| 6 | Expiry tracking + email reminder | Done (including preview template) | Done: expiring items shown on Home and Fridge; Supabase Edge Function (`daily-reminder`) + `pg_cron` sends daily expiry/inactivity reminders and weekly summaries via Gmail SMTP. |
| 7 | Recipes + weekly AI meal plan | Done | Not yet (still using `DemoData`). |
| 8 | Shared shopping list | Done | Done: `shopping_list_items` with RLS, full CRUD (add, check/uncheck, delete) via Supabase Data API, and live Supabase Realtime sync across household members. |
| 9 | Calories and nutrition | Done | Partly: FatSecret + full TKPI table (1,146 ingredients) lookup works and item detail shows TKPI nutrition per 100 g. The `/gizi` summary page (fridge totals and personal consumption logs) still uses `DemoData`. |
| 10 | Activity history | Done | Done: Postgres trigger records added, used, edited, cooked, and discarded items into `activity_logs`, shown on `/riwayat` and Home. |
| 11 | Food waste analytics + AI summary | Done | Partly: monthly wasted value, cooked/discarded counts, 6-month chart, and top wasted items come from Postgres security-invoker views (`FoodWasteService`). Only the AI summary text still uses `DemoData`. |
| 12 | Installable PWA + offline fallback | Done | Done: PNG/maskable/Apple touch icons, `manifest.webmanifest`, static-asset Service Worker (`public/sw.js`), and offline fallback page (`public/offline.html`). |

## Tech Stack

### In use

| Part | Owner |
| --- | --- |
| Frontend: Laravel 12 Blade + Tailwind CSS v4, installable PWA (icons, manifest, Service Worker, offline page) | Hans |
| Supabase PostgreSQL + Row Level Security (policies, private helper functions, column-level grants, triggers, security-invoker views) | Grevi, Hans (initial public-table RLS & `shopping_list_items`) |
| Supabase Auth (email + password, session refresh, household middleware, browser session token endpoint) | Grevi (core Auth), Hans (auth UI & `/sesi/token`) |
| Supabase Data API (REST + RPC with the user's access token, so RLS applies) | Joel, Grevi (fridge), Hans (shopping list) |
| Supabase Realtime (`postgres_changes` via `@supabase/supabase-js` for live shopping list and fridge sync) | Hans (shopping list), Grevi (fridge) |
| Supabase Storage (private household buckets `receipts` & `fridge-photos` with signed URLs) | Grevi, Joel |
| Supabase Edge Functions + `pg_cron` (`daily-reminder` daily & weekly emails via Gmail SMTP) | Grevi |
| Gemini API (Gemini 3.5 Flash-Lite) for receipt scanning via Laravel database queue | Joel, Hans (review UI) |
| FatSecret Platform API + TKPI table (Kemenkes) for nutrition data | Joel |
| CloudPanel + GitHub Actions CI/CD (`.github/workflows/deploy.yaml`) | Joel, Grevi |

### Planned / Remaining

- Merge `Fridge-scan` branch (Gemini Vision fridge photo scan) into `main`
- Gemini API integration for recipes, weekly meal plan, and food waste AI summary
- Nutrition aggregation on `/gizi` and personal consumption logging (`consumption_logs`)
- Production server `php8.4-pgsql` extension and SSH deploy access on CloudPanel

## How to run

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Fill in the Supabase (`DB_*`, `SUPABASE_*`), FatSecret (`FATSECRET_*`), and Gemini (`GEMINI_API_KEY`, `GEMINI_MODEL`) values in `.env`.

The shared Supabase database is already migrated and seeded, so `php artisan migrate` only reports "Nothing to migrate". Never run `migrate:fresh` against it: it deletes every table. For a new, empty Supabase project:

```bash
php artisan migrate
php artisan db:seed --class=TkpiItemsSeeder
```

With Laravel Herd, open `http://sedapur.test`. To process receipt scans locally, run the queue worker in a separate terminal:

```bash
php artisan queue:work
```

Without Herd (for example on Windows when `php artisan serve` fails), run these in separate terminals and open `http://127.0.0.1:8000`:

```bash
php -S 127.0.0.1:8000 -t public
npm run dev
```

For local development, `SESSION_DRIVER=file` and `CACHE_STORE=file` keep each page load from depending on the connection to the database.

## Pages

| URL | Screen |
| --- | --- |
| `/masuk`, `/daftar`, `/rumah/mulai` | Sign in, sign up, create or join a household |
| `/` | Home (real expiring items, waste stats, recent activity; menu & nutrition cards still sample data) |
| `/kulkas`, `/kulkas/{id}`, `/kulkas/{id}/ubah` | Fridge + item detail (right panel on desktop) + edit item |
| `/items/manual` | Manual ingredient input |
| `/scan/{scan}/processing`, `/scan/{scan}/hasil`, `/scan/{scan}/gagal` | Receipt scan in progress, editable review results, scan failed |
| `/menu`, `/resep`, `/resep/{id}` | Weekly menu, recipe list, recipe detail (sample data) |
| `/belanja` | Shared shopping list (live Realtime) |
| `/gizi` | Nutrition & calories (sample data) |
| `/food-waste` | Food waste analytics (real DB views; AI summary card sample data) |
| `/riwayat` | Activity history |
| `/rumah` | Household & account |
| `/pratinjau/email-pengingat` | Preview of the daily reminder email |
| `/offline.html` | Static offline fallback page served by the Service Worker |

## Structure

- `app/Services/SupabaseService.php` — Supabase Auth (`/auth/v1`), Data API (`/rest/v1`), RPC, and Storage (`/storage/v1`) calls using the publishable key and the user's access token.
- `app/Services/HouseholdService.php`, `app/Http/Controllers/HouseholdController.php` — create a household, join with an invite code, manage members, leave household.
- `app/Services/FoodItemService.php`, `app/Http/Controllers/FridgeController.php` — fridge CRUD, partial usage RPC, status changes, and TKPI nutrition lookup on item detail.
- `app/Services/ShoppingListService.php`, `app/Http/Controllers/ShoppingListController.php` — shared shopping list CRUD and partial list endpoint for Realtime reloads.
- `app/Services/FoodWasteService.php`, `app/Services/ActivityLogService.php` — monthly food waste views and trigger-backed activity timeline.
- `app/Services/GeminiReceiptService.php`, `app/Jobs/ProcessReceiptScan.php`, `app/Http/Controllers/ReceiptScanController.php` — receipt photo upload, async Gemini extraction, review, and save to fridge.
- `app/Http/Controllers/AuthController.php`, `app/Http/Requests/` — sign in, sign up, sign out, `/sesi/token`, and Form Request validation.
- `app/Http/Middleware/` — `supabase.auth` (signed in + token refresh + 401 for JSON/AJAX), `supabase.guest`, `household` (has an active household).
- `app/Support/SupabaseSession.php` — the user's Supabase session stored server-side in the Laravel session.
- `app/Http/Controllers/NutritionController.php`, `app/Services/NutritionLookupService.php`, `FatSecretService.php`, `TkpiService.php` — nutrition lookup.
- `app/Support/DemoData.php`, `app/Http/Controllers/PageController.php` — formatting helpers, category constants, and remaining sample data (`menu`, `recipes`, `nutrition`, AI summary).
- `database/migrations/` — Laravel tables, `tkpi_items`, household, fridge, receipt scans, storage buckets/policies, shopping list, activity logs, food waste views, and `pg_cron` schedule.
- `database/data/tkpi.csv`, `database/seeders/TkpiItemsSeeder.php` — 1,146 TKPI ingredients (Kemenkes, via the cleaned dataset of the Jatim Melaju 2026 research team).
- `supabase/functions/daily-reminder/index.ts` — Supabase Edge Function for daily expiry/inactivity reminders and weekly summaries.
- `resources/views/components/layouts/` — `app` layout (sidebar + bottom nav) and `guest` layout (sign-in pages).
- `resources/css/app.css` — warm terracotta & emerald colour tokens and utility components (`btn-*`, `card`, `input`, `chip`, `badge`).
- `resources/js/` — `app.js` (dialogs, invite code boxes, share/copy, SW registration), `realtime-list.js` (Supabase Realtime for `/belanja` and `/kulkas`), `receipt-review.js`.
- `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/`, `public/apple-touch-icon.png` — PWA manifest, icons, static-asset Service Worker, and offline page.

## Tests

```bash
npm run build
php artisan test
```

Tests run on an in-memory SQLite database and fake every call to Supabase, FatSecret, and Gemini, so they never touch the shared database. `npm run build` is needed first, otherwise page tests fail with `ViteManifestNotFoundException`.
