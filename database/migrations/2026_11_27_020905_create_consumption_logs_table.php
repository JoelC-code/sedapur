<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan konsumsi pribadi (halaman /gizi, tab "Konsumsi saya").
 *
 * - Satu baris = satu porsi yang dimakan: nama, berat (g), kalori dan makro hasil hitung server
 *   (FatSecret / TKPI per 100 g x berat), plus sumber datanya.
 * - Data pribadi: RLS hanya mengizinkan pemilik baris membaca, menambah dan menghapus.
 *   Anggota serumah tidak bisa melihat catatan makan satu sama lain.
 * - Tidak ada update: salah catat dihapus lalu dicatat ulang.
 * - View consumption_daily (security_invoker) menjumlahkan per user per hari (WIB).
 *
 * Bagian SQL hanya jalan di Postgres. Di SQLite (tes) cukup tabelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_logs', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreignId('food_item_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('grams', 7, 1);
            $table->decimal('calories', 7, 1);
            $table->decimal('protein_g', 6, 1);
            $table->decimal('carbs_g', 6, 1);
            $table->decimal('fat_g', 6, 1);
            $table->string('source', 12);
            $table->timestampTz('consumed_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();

            // Query halaman gizi: catatan satu user, terbaru dulu.
            $table->index(['user_id', 'consumed_at']);
            $table->index('household_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Constraint, foreign key ke Supabase Auth, default ---------------------------

            alter table public.consumption_logs
                add constraint consumption_logs_grams_check check (grams > 0),
                add constraint consumption_logs_nutrients_check
                    check (calories >= 0 and protein_g >= 0 and carbs_g >= 0 and fat_g >= 0),
                add constraint consumption_logs_source_check
                    check (source in ('fatsecret', 'tkpi', 'estimasi_ai')),
                add constraint consumption_logs_user_id_fkey
                    foreign key (user_id) references auth.users (id) on delete cascade,
                alter column user_id set default auth.uid();

            -- 2. Row Level Security: hanya pemilik catatan --------------------------------------

            alter table public.consumption_logs enable row level security;

            create policy "User dapat melihat catatan makannya sendiri"
                on public.consumption_logs for select
                to authenticated
                using (user_id = (select auth.uid()));

            create policy "User dapat mencatat makan di rumahnya"
                on public.consumption_logs for insert
                to authenticated
                with check (user_id = (select auth.uid()) and (select private.is_household_member(household_id)));

            create policy "User dapat menghapus catatan makannya sendiri"
                on public.consumption_logs for delete
                to authenticated
                using (user_id = (select auth.uid()));

            -- 3. Grant (per kolom: user_id dan waktu diisi database) ----------------------------

            revoke all on public.consumption_logs from anon, authenticated;

            grant select, delete on public.consumption_logs to authenticated;
            grant insert (household_id, food_item_id, name, grams, calories, protein_g, carbs_g, fat_g, source)
                on public.consumption_logs to authenticated;

            do $$
            begin
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.consumption_logs', 'id'));
            end;
            $$;

            -- 4. Total per hari (WIB), dengan hak user pemanggil --------------------------------

            create or replace view public.consumption_daily
            with (security_invoker = true)
            as
            select
                c.user_id,
                (c.consumed_at at time zone 'Asia/Jakarta')::date as day,
                sum(c.calories) as calories,
                sum(c.protein_g) as protein_g,
                sum(c.carbs_g) as carbs_g,
                sum(c.fat_g) as fat_g
            from public.consumption_logs c
            group by c.user_id, day;

            revoke all on public.consumption_daily from anon, authenticated;
            grant select on public.consumption_daily to authenticated;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop view if exists public.consumption_daily');
        }

        Schema::dropIfExists('consumption_logs');
    }
};
