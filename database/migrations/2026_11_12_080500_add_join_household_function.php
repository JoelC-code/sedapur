<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gabung ke rumah memakai kode undangan.
 *
 * User yang belum jadi anggota tidak bisa melihat rumah itu (RLS households) dan tidak punya
 * hak insert ke household_members, jadi prosesnya lewat fungsi security definer di schema
 * private (tidak terbuka di Data API). Yang dipanggil lewat /rest/v1/rpc/join_household adalah
 * pembungkus security invoker di public, hanya untuk role authenticated.
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
            create or replace function private.join_household(p_invite_code text)
            returns bigint
            language plpgsql
            volatile
            security definer
            set search_path = ''
            as $$
            declare
                v_user_id uuid := (select auth.uid());
                v_household_id bigint;
            begin
                if v_user_id is null then
                    raise exception 'Harus masuk dulu' using errcode = '42501';
                end if;

                select h.id into v_household_id
                from public.households h
                where h.invite_code = upper(trim(p_invite_code));

                if v_household_id is null then
                    raise exception 'Kode undangan tidak ditemukan' using errcode = 'P0002';
                end if;

                -- Sudah jadi anggota? Tidak apa-apa, cukup kembalikan rumahnya.
                insert into public.household_members (household_id, user_id, role)
                values (v_household_id, v_user_id, 'member')
                on conflict (household_id, user_id) do nothing;

                return v_household_id;
            end;
            $$;

            create or replace function public.join_household(p_invite_code text)
            returns bigint
            language sql
            volatile
            security invoker
            set search_path = ''
            as $$
                select private.join_household(p_invite_code);
            $$;

            revoke execute on function private.join_household(text) from public, anon;
            revoke execute on function public.join_household(text) from public, anon;
            grant execute on function private.join_household(text) to authenticated;
            grant execute on function public.join_household(text) to authenticated;

            -- Data API (PostgREST) membaca ulang daftar fungsi agar RPC baru langsung terlihat.
            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop function if exists public.join_household(text);
            drop function if exists private.join_household(text);
            notify pgrst, 'reload schema';
        SQL);
    }
};
