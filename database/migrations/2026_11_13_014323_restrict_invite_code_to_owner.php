<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kode undangan hanya boleh dilihat owner rumah.
 *
 * RLS bekerja per baris, sedangkan kode adalah satu kolom di baris yang juga boleh dibaca member.
 * Jadi hak baca kolom invite_code dicabut dari role authenticated, dan owner mengambil kodenya
 * lewat fungsi household_invite_code() yang memeriksa peran owner di household_members.
 *
 * Hanya jalan di Postgres. Di SQLite (tes) migration ini dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            revoke select on public.households from authenticated;
            grant select (id, name, created_by, created_at, updated_at) on public.households to authenticated;

            create or replace function private.household_invite_code(p_household_id bigint)
            returns text
            language sql
            stable
            security definer
            set search_path = ''
            as $$
                select h.invite_code
                from public.households h
                join public.household_members m on m.household_id = h.id
                where h.id = p_household_id
                  and m.user_id = (select auth.uid())
                  and m.role = 'owner';
            $$;

            create or replace function public.household_invite_code(p_household_id bigint)
            returns text
            language sql
            stable
            security invoker
            set search_path = ''
            as $$
                select private.household_invite_code(p_household_id);
            $$;

            revoke execute on function private.household_invite_code(bigint) from public, anon;
            revoke execute on function public.household_invite_code(bigint) from public, anon;
            grant execute on function private.household_invite_code(bigint) to authenticated;
            grant execute on function public.household_invite_code(bigint) to authenticated;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop function if exists public.household_invite_code(bigint);
            drop function if exists private.household_invite_code(bigint);
            grant select on public.households to authenticated;
            notify pgrst, 'reload schema';
        SQL);
    }
};
