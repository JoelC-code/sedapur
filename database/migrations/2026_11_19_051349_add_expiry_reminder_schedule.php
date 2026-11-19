<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengingat email harian dan rangkuman mingguan (Edge Function daily-reminder + pg_cron).
 *
 * - profiles.last_seen_at: kapan user terakhir membuka aplikasi. Diisi lewat RPC touch_last_seen
 *   (waktu dari server database, bukan dari client). Dipakai untuk mengingatkan user yang 2 hari
 *   tidak membuka aplikasi agar mencatat belanja atau pemakaian.
 * - Grant SELECT ke service_role: Edge Function membaca data semua rumah dengan secret key.
 *   service_role melewati RLS, tetapi tetap butuh hak tabel karena auto-expose dimatikan.
 * - pg_cron memanggil Edge Function lewat pg_net setiap hari 19.00 WIB (12.00 UTC).
 *   URL proyek dan secret cron dibaca dari Supabase Vault, jadi tidak pernah masuk repo.
 *   Isi Vault sekali lewat SQL editor (lihat catatan deploy di supabase/functions/daily-reminder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestampTz('last_seen_at')->nullable();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. last_seen_at: hanya baris sendiri, hanya kolom ini, nilai dari now() di server.
            create policy "User dapat memperbarui profil sendiri"
                on public.profiles for update
                to authenticated
                using (id = (select auth.uid()))
                with check (id = (select auth.uid()));

            grant update (last_seen_at) on public.profiles to authenticated;

            create or replace function public.touch_last_seen()
            returns void
            language sql
            volatile
            security invoker
            set search_path = ''
            as $$
                update public.profiles set last_seen_at = now() where id = (select auth.uid());
            $$;

            revoke execute on function public.touch_last_seen() from public, anon;
            grant execute on function public.touch_last_seen() to authenticated;

            -- 2. Edge Function (secret key) hanya perlu membaca.
            grant select on public.households, public.household_members, public.food_items, public.profiles to service_role;

            -- 3. Jadwal. Senin-Sabtu: pengingat harian. Minggu: rangkuman mingguan (sudah termasuk bahan yang mendesak).
            create extension if not exists pg_cron;
            create extension if not exists pg_net with schema extensions;

            select cron.schedule('sedapur-daily-reminder', '0 12 * * 1-6', $cron$
                select net.http_post(
                    url := (select decrypted_secret from vault.decrypted_secrets where name = 'sedapur_project_url') || '/functions/v1/daily-reminder',
                    headers := jsonb_build_object(
                        'Content-Type', 'application/json',
                        'x-cron-secret', (select decrypted_secret from vault.decrypted_secrets where name = 'sedapur_cron_secret')
                    ),
                    body := '{"type": "daily"}'::jsonb,
                    timeout_milliseconds := 60000
                );
            $cron$);

            select cron.schedule('sedapur-weekly-summary', '0 12 * * 0', $cron$
                select net.http_post(
                    url := (select decrypted_secret from vault.decrypted_secrets where name = 'sedapur_project_url') || '/functions/v1/daily-reminder',
                    headers := jsonb_build_object(
                        'Content-Type', 'application/json',
                        'x-cron-secret', (select decrypted_secret from vault.decrypted_secrets where name = 'sedapur_cron_secret')
                    ),
                    body := '{"type": "weekly"}'::jsonb,
                    timeout_milliseconds := 60000
                );
            $cron$);

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                select cron.unschedule(jobname) from cron.job where jobname in ('sedapur-daily-reminder', 'sedapur-weekly-summary');
                revoke select on public.households, public.household_members, public.food_items, public.profiles from service_role;
                drop function if exists public.touch_last_seen();
                revoke update (last_seen_at) on public.profiles from authenticated;
                drop policy if exists "User dapat memperbarui profil sendiri" on public.profiles;
            SQL);
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
