# Sedapur

A mobile-first web app for a fridge shared by a household or boarding house (kos): track expiry dates, use ingredients before they go to waste, and see how much money is thrown away each month.

Group 7\
Cloud Computing\
Alvaro Grevi Kumesan\
Hans Vere Liem\
Joel Christopher Gerald Tjokrowiharjo

## Feature progress

Every screen is built from the Figma design (phone and desktop layouts). Sign-in, sign-up and the household (create or join) are connected to Supabase. The other screens still show sample data from `app/Support/DemoData.php` until each feature's backend is connected. Ingredients are shown as text with a category label (no emoji), because Unicode food emoji only cover about 59% of the 1,146 TKPI ingredients.

| # | Feature | UI | Backend / data |
| --- | --- | --- | --- |
| 1 | Sign up, sign in, sign out | Done | Done: email + password through Supabase Auth, session refresh, route protection. Google sign-in not yet. |
| 2 | Shared fridge + invite code | Done | Partly: `households`, `household_members` and `food_items` tables in Supabase with Row Level Security. Creating a household and joining with an invite code work (`join_household` function called through the Data API). Fridge items and Realtime sync not yet. |
| 3 | Receipt scan (Gemini Vision) | Done | Not yet, waiting for the Gemini API |
| 4 | Fridge photo scan (Gemini Vision) | Done | Not yet, waiting for the Gemini API |
| 5 | Manual input + ingredient autocomplete | Done | Partly: nutrition search API `/api/nutrition/search` (FatSecret, then TKPI). Not yet wired to the form. |
| 6 | Expiry tracking + email reminder | Done (including the email template) | Not yet (Edge Function + pg_cron) |
| 7 | Recipes + weekly AI meal plan | Done | Not yet, waiting for the Gemini API |
| 8 | Shared shopping list | Done | Not yet |
| 9 | Calories and nutrition | Done | Partly: nutrition sources FatSecret + full TKPI table (1,146 ingredients). Totals and consumption logging not yet. |
| 10 | Activity history | Done | Not yet |
| 11 | Food waste analytics + AI summary | Done | Not yet |

## Tech Stack

### In use

| Part | Owner |
| --- | --- |
| Frontend: Laravel 12 Blade + Tailwind CSS v4, PWA manifest | Hans |
| Supabase PostgreSQL + Row Level Security (policies, helper functions in a private schema, column-level grants) | Grevi |
| Supabase Auth (email + password) | Hans |
| Supabase Data API (REST + RPC with the user's access token, so RLS applies) | Joel |
| FatSecret Platform API + TKPI table (Kemenkes) for nutrition data | Joel |

### Planned

- Supabase Storage
- Supabase Realtime
- Supabase Edge Functions + pg_cron
- Gemini API (Gemini 3.1 Flash-Lite) for receipt scan, fridge scan, recipes, menu and summaries
- CloudPanel + GitHub Actions for automatic deploy

## How to run

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

The shared Supabase database is already migrated and seeded, so `php artisan migrate` only reports "Nothing to migrate". Never run `migrate:fresh` against it: it deletes every table. For a new, empty Supabase project:

```bash
php artisan migrate
php artisan db:seed --class=TkpiItemsSeeder
```

With Laravel Herd, open `http://sedapur.test`. Without Herd (for example on Windows when `php artisan serve` fails), run these in two terminals and open `http://127.0.0.1:8000`:

```bash
php -S 127.0.0.1:8000 -t public
npm run dev
```

For local development, `SESSION_DRIVER=file` and `CACHE_STORE=file` keep each page load from depending on the connection to the database.

## Pages

| URL | Screen |
| --- | --- |
| `/masuk`, `/daftar`, `/rumah/mulai` | Sign in, sign up, create or join a household |
| `/` | Home |
| `/kulkas`, `/kulkas/{id}` | Fridge + item detail (right panel on desktop) |
| `/scan`, `/scan/gagal`, `/scan/hasil`, `/bahan/manual` | Scan in progress, scan failed, review scan results, manual input |
| `/menu`, `/resep`, `/resep/{id}` | Weekly menu, recipe list, recipe detail + log portions |
| `/belanja` | Shared shopping list |
| `/gizi` | Nutrition & calories (my intake / fridge contents) |
| `/food-waste` | Food waste analytics |
| `/riwayat` | Activity history |
| `/rumah` | Household & account |
| `/pratinjau/email-pengingat` | Preview of the daily reminder email |

## Structure

- `app/Services/SupabaseService.php` — Supabase Auth (`/auth/v1`) and Data API (`/rest/v1`) calls, always with the publishable key and the user's access token.
- `app/Services/HouseholdService.php`, `app/Http/Controllers/HouseholdController.php` — create a household, join with an invite code, load the active household.
- `app/Http/Controllers/AuthController.php`, `app/Http/Requests/` — sign in, sign up, sign out, and form validation.
- `app/Http/Middleware/` — `supabase.auth` (signed in + token refresh), `supabase.guest`, `household` (has a household).
- `app/Support/SupabaseSession.php` — the user's Supabase session stored server-side in the Laravel session.
- `app/Http/Controllers/NutritionController.php`, `app/Services/FatSecretService.php`, `app/Services/TkpiService.php` — nutrition lookup.
- `app/Support/DemoData.php` — sample data shaped like the ERD; replaced by Supabase queries feature by feature.
- `app/Http/Controllers/PageController.php` — the pages that still use sample data.
- `database/migrations/` — Laravel tables, `tkpi_items`, household and fridge tables with RLS, and the `join_household` function.
- `database/data/tkpi.csv`, `database/seeders/TkpiItemsSeeder.php` — 1,146 TKPI ingredients (Kemenkes, via the cleaned dataset of the Jatim Melaju 2026 research team).
- `resources/views/components/layouts/` — `app` layout (sidebar + bottom nav) and `guest` layout (sign-in pages).
- `resources/css/app.css` — colour tokens and components (buttons, inputs, chips, badges) from the UI kit.

## Tests

```bash
npm run build
php artisan test
```

Tests run on an in-memory SQLite database and fake every call to Supabase and FatSecret, so they never touch the shared database. `npm run build` is needed first, otherwise page tests fail with `ViteManifestNotFoundException`.
