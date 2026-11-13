<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profil publik setiap akun (nama tampilan), agar anggota satu rumah bisa saling melihat nama.
 *
 * auth.users tidak terbuka lewat Data API, jadi nama disalin ke public.profiles oleh trigger
 * saat akun dibuat. Nama hanya untuk tampilan; peran (owner/member) tetap dari household_members.
 * RLS: user hanya melihat profilnya sendiri dan profil orang yang satu rumah dengannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 80);
            $table->timestampTz('created_at')->useCurrent();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            alter table public.profiles
                add constraint profiles_id_fkey
                foreign key (id) references auth.users (id) on delete cascade;

            -- Isi profil untuk akun yang sudah ada sebelum migration ini.
            insert into public.profiles (id, full_name)
            select u.id,
                   left(coalesce(nullif(trim(u.raw_user_meta_data ->> 'full_name'), ''), split_part(u.email, '@', 1), 'Anggota'), 80)
            from auth.users u
            on conflict (id) do nothing;

            -- Akun baru otomatis mendapat profil.
            create or replace function private.create_profile_for_new_user()
            returns trigger
            language plpgsql
            security definer
            set search_path = ''
            as $$
            begin
                insert into public.profiles (id, full_name)
                values (
                    new.id,
                    left(coalesce(nullif(trim(new.raw_user_meta_data ->> 'full_name'), ''), split_part(new.email, '@', 1), 'Anggota'), 80)
                )
                on conflict (id) do nothing;
                return new;
            end;
            $$;

            revoke execute on function private.create_profile_for_new_user() from public, anon, authenticated;

            create trigger on_auth_user_created_create_profile
                after insert on auth.users
                for each row execute function private.create_profile_for_new_user();

            -- Relasi ke profiles agar Data API bisa menyertakan nama anggota:
            -- /rest/v1/household_members?select=role,profiles(full_name)
            alter table public.household_members
                add constraint household_members_user_id_profiles_fkey
                foreign key (user_id) references public.profiles (id) on delete cascade;

            -- Apakah user yang login satu rumah dengan $p_user_id?
            create or replace function private.shares_household_with(p_user_id uuid)
            returns boolean
            language sql
            stable
            security definer
            set search_path = ''
            as $$
                select exists (
                    select 1
                    from public.household_members mine
                    join public.household_members theirs on theirs.household_id = mine.household_id
                    where mine.user_id = (select auth.uid())
                      and theirs.user_id = p_user_id
                );
            $$;

            revoke execute on function private.shares_household_with(uuid) from public, anon;
            grant execute on function private.shares_household_with(uuid) to authenticated;

            alter table public.profiles enable row level security;

            create policy "User dapat melihat profil sendiri dan teman serumah"
                on public.profiles for select
                to authenticated
                using (id = (select auth.uid()) or private.shares_household_with(id));

            revoke all on public.profiles from anon;
            grant select on public.profiles to authenticated;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                alter table public.household_members drop constraint if exists household_members_user_id_profiles_fkey;
                drop trigger if exists on_auth_user_created_create_profile on auth.users;
                drop function if exists private.create_profile_for_new_user();
                drop function if exists private.shares_household_with(uuid);
            SQL);
        }

        Schema::dropIfExists('profiles');
    }
};
