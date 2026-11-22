<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * receipt_scans siap dipakai lewat Data API dengan RLS.
 *
 * - Kolom household_id: scan milik satu rumah, sama seperti foto struk di bucket receipts
 *   (<household_id>/<uuid>.<ext>). Diisi ulang dari path foto, atau dari keanggotaan user.
 * - Foreign key user_id ke auth.users, constraint status, index untuk policy dan foreign key.
 * - RLS: user hanya melihat dan mengubah scan miliknya sendiri, di rumah yang masih ia ikuti.
 * - Grant per kolom ke authenticated: result (hasil Gemini) tidak bisa ditulis client;
 *   yang mengisinya job ProcessReceiptScan di server (jalur sistem, koneksi langsung).
 *
 * Bagian SQL hanya jalan di Postgres. Di SQLite (tes) cukup kolomnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_scans', function (Blueprint $table) {
            // Foreign key ke households ditambahkan di SQL Postgres di bawah, setelah kolomnya terisi.
            $table->unsignedBigInteger('household_id')->nullable()->after('id')->index();
            $table->index('user_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Isi household_id untuk scan lama ----------------------------------------------

            -- Foto baru disimpan di <household_id>/<uuid>.<ext>: ambil angka di depan path.
            update public.receipt_scans r
            set household_id = split_part(r.image_path, '/', 1)::bigint
            where r.household_id is null
              and r.image_path ~ '^[0-9]+/'
              and exists (select 1 from public.households h where h.id = split_part(r.image_path, '/', 1)::bigint);

            -- Sisanya (foto gagal diunggah atau path lama): rumah yang diikuti user.
            update public.receipt_scans r
            set household_id = (
                select min(m.household_id) from public.household_members m where m.user_id = r.user_id
            )
            where r.household_id is null;

            alter table public.receipt_scans alter column household_id set not null;

            -- 2. Constraint, foreign key ke Supabase Auth, default ------------------------------

            alter table public.receipt_scans
                add constraint receipt_scans_household_id_fkey
                    foreign key (household_id) references public.households (id) on delete cascade,
                add constraint receipt_scans_status_check check (status in ('processing', 'completed', 'failed')),
                add constraint receipt_scans_user_id_fkey
                    foreign key (user_id) references auth.users (id) on delete cascade,
                alter column user_id set default auth.uid();

            -- 3. Row Level Security -----------------------------------------------------------

            alter table public.receipt_scans enable row level security;

            create policy "User dapat melihat scan struk miliknya"
                on public.receipt_scans for select
                to authenticated
                using (user_id = (select auth.uid()) and (select private.is_household_member(household_id)));

            create policy "User dapat menambah scan struk di rumahnya"
                on public.receipt_scans for insert
                to authenticated
                with check (user_id = (select auth.uid()) and (select private.is_household_member(household_id)));

            create policy "User dapat mengubah scan struk miliknya"
                on public.receipt_scans for update
                to authenticated
                using (user_id = (select auth.uid()) and (select private.is_household_member(household_id)))
                with check (user_id = (select auth.uid()) and (select private.is_household_member(household_id)));

            -- 4. Grant (per kolom: result, user_id dan household_id tidak bisa diubah client) ----

            revoke all on public.receipt_scans from anon, authenticated;

            grant select on public.receipt_scans to authenticated;
            grant insert (household_id, image_path) on public.receipt_scans to authenticated;
            grant update (image_path, status, error_message) on public.receipt_scans to authenticated;

            do $$
            begin
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.receipt_scans', 'id'));
            end;
            $$;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                drop policy if exists "User dapat melihat scan struk miliknya" on public.receipt_scans;
                drop policy if exists "User dapat menambah scan struk di rumahnya" on public.receipt_scans;
                drop policy if exists "User dapat mengubah scan struk miliknya" on public.receipt_scans;
                revoke all on public.receipt_scans from anon, authenticated;
                alter table public.receipt_scans
                    drop constraint if exists receipt_scans_household_id_fkey,
                    drop constraint if exists receipt_scans_status_check,
                    drop constraint if exists receipt_scans_user_id_fkey,
                    alter column user_id drop default;
            SQL);
        }

        Schema::table('receipt_scans', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['household_id']);
            $table->dropColumn('household_id');
        });
    }
};
