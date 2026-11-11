<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keamanan tabel rumah dan isi kulkas di Supabase.
 *
 * - Foreign key ke auth.users (akun Supabase Auth).
 * - Fungsi helper di schema private (tidak terbuka di Data API).
 * - Trigger: pembuat rumah otomatis jadi owner; updated_at dan status_changed_* terisi sendiri.
 * - Row Level Security + policy: anggota hanya bisa membaca/mengubah data rumahnya sendiri.
 * - Grant per kolom ke role authenticated (auto-expose di Supabase dimatikan).
 * - food_items masuk publication supabase_realtime agar isi kulkas sinkron ke semua anggota.
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
            -- 1. Foreign key ke akun Supabase Auth -------------------------------------------

            alter table public.households
                add constraint households_created_by_fkey
                foreign key (created_by) references auth.users (id) on delete set null;

            alter table public.household_members
                add constraint household_members_user_id_fkey
                foreign key (user_id) references auth.users (id) on delete cascade;

            alter table public.food_items
                add constraint food_items_added_by_fkey
                foreign key (added_by) references auth.users (id) on delete set null,
                add constraint food_items_status_changed_by_fkey
                foreign key (status_changed_by) references auth.users (id) on delete set null;

            -- 2. Schema private: fungsi internal, tidak bisa dipanggil lewat Data API ----------

            create schema if not exists private;
            grant usage on schema private to authenticated;

            -- Apakah user yang sedang login anggota rumah ini?
            -- security definer: membaca household_members tanpa terkena RLS tabel itu sendiri
            -- (kalau tidak, policy household_members memanggil dirinya sendiri tanpa henti).
            create or replace function private.is_household_member(p_household_id bigint)
            returns boolean
            language sql
            stable
            security definer
            set search_path = ''
            as $$
                select exists (
                    select 1
                    from public.household_members m
                    where m.household_id = p_household_id
                      and m.user_id = (select auth.uid())
                );
            $$;

            create or replace function private.is_household_owner(p_household_id bigint)
            returns boolean
            language sql
            stable
            security definer
            set search_path = ''
            as $$
                select exists (
                    select 1
                    from public.household_members m
                    where m.household_id = p_household_id
                      and m.user_id = (select auth.uid())
                      and m.role = 'owner'
                );
            $$;

            revoke execute on function private.is_household_member(bigint) from public, anon;
            revoke execute on function private.is_household_owner(bigint) from public, anon;
            grant execute on function private.is_household_member(bigint) to authenticated;
            grant execute on function private.is_household_owner(bigint) to authenticated;

            -- Kode undangan 6 karakter, tanpa huruf/angka yang mirip (O/0, I/1/L).
            create or replace function private.generate_invite_code()
            returns text
            language plpgsql
            volatile
            set search_path = ''
            as $$
            declare
                alphabet constant text := 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
                code text := '';
            begin
                for i in 1..6 loop
                    code := code || substr(alphabet, 1 + floor(random() * length(alphabet))::int, 1);
                end loop;
                return code;
            end;
            $$;

            revoke execute on function private.generate_invite_code() from public, anon;
            grant execute on function private.generate_invite_code() to authenticated;

            -- 3. Nilai default yang diisi database, bukan oleh client -------------------------

            alter table public.households
                alter column created_by set default auth.uid(),
                alter column invite_code set default private.generate_invite_code();

            alter table public.food_items
                alter column added_by set default auth.uid();

            -- 4. Trigger ----------------------------------------------------------------------

            -- Pembuat rumah otomatis menjadi owner.
            create or replace function private.add_household_owner()
            returns trigger
            language plpgsql
            security definer
            set search_path = ''
            as $$
            begin
                insert into public.household_members (household_id, user_id, role)
                values (new.id, new.created_by, 'owner');
                return new;
            end;
            $$;

            create trigger households_add_owner
                after insert on public.households
                for each row
                when (new.created_by is not null)
                execute function private.add_household_owner();

            -- updated_at selalu waktu perubahan terakhir.
            create or replace function private.set_updated_at()
            returns trigger
            language plpgsql
            set search_path = ''
            as $$
            begin
                new.updated_at := now();
                return new;
            end;
            $$;

            create trigger households_set_updated_at
                before update on public.households
                for each row execute function private.set_updated_at();

            create trigger food_items_set_updated_at
                before update on public.food_items
                for each row execute function private.set_updated_at();

            -- Catat kapan dan oleh siapa bahan ditandai dimasak/dibuang (untuk riwayat dan analitik food waste).
            create or replace function private.track_food_item_status()
            returns trigger
            language plpgsql
            set search_path = ''
            as $$
            begin
                if new.status is distinct from old.status then
                    new.status_changed_at := now();
                    new.status_changed_by := auth.uid();
                end if;
                return new;
            end;
            $$;

            create trigger food_items_track_status
                before update on public.food_items
                for each row execute function private.track_food_item_status();

            -- 5. Row Level Security -------------------------------------------------------------

            alter table public.households enable row level security;
            alter table public.household_members enable row level security;
            alter table public.food_items enable row level security;

            -- households
            create policy "Anggota dapat melihat rumahnya"
                on public.households for select
                to authenticated
                using (private.is_household_member(id) or created_by = (select auth.uid()));

            create policy "User login dapat membuat rumah"
                on public.households for insert
                to authenticated
                with check (created_by = (select auth.uid()));

            create policy "Owner dapat mengubah rumah"
                on public.households for update
                to authenticated
                using (private.is_household_owner(id))
                with check (private.is_household_owner(id));

            create policy "Owner dapat menghapus rumah"
                on public.households for delete
                to authenticated
                using (private.is_household_owner(id));

            -- household_members (baris baru dibuat oleh trigger dan fungsi gabung, bukan oleh client)
            create policy "Anggota dapat melihat sesama anggota"
                on public.household_members for select
                to authenticated
                using (private.is_household_member(household_id));

            create policy "Anggota dapat keluar, owner dapat mengeluarkan"
                on public.household_members for delete
                to authenticated
                using (user_id = (select auth.uid()) or private.is_household_owner(household_id));

            -- food_items
            create policy "Anggota dapat melihat isi kulkas rumahnya"
                on public.food_items for select
                to authenticated
                using (private.is_household_member(household_id));

            create policy "Anggota dapat menambah bahan ke kulkas rumahnya"
                on public.food_items for insert
                to authenticated
                with check (private.is_household_member(household_id) and added_by = (select auth.uid()));

            create policy "Anggota dapat mengubah bahan di kulkas rumahnya"
                on public.food_items for update
                to authenticated
                using (private.is_household_member(household_id))
                with check (private.is_household_member(household_id));

            create policy "Anggota dapat menghapus bahan di kulkas rumahnya"
                on public.food_items for delete
                to authenticated
                using (private.is_household_member(household_id));

            -- 6. Grant (auto-expose dimatikan, jadi akses Data API dibuka manual per kolom) ----

            revoke all on public.households, public.household_members, public.food_items from anon;

            grant select, delete on public.households to authenticated;
            grant insert (name) on public.households to authenticated;
            grant update (name) on public.households to authenticated;

            grant select, delete on public.household_members to authenticated;

            grant select, delete on public.food_items to authenticated;
            grant insert (household_id, name, category, emoji, quantity, unit, weight_grams, price,
                          purchased_at, expires_at, input_source)
                on public.food_items to authenticated;
            grant update (name, category, emoji, quantity, unit, weight_grams, price,
                          purchased_at, expires_at, status)
                on public.food_items to authenticated;

            do $$
            begin
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.households', 'id'));
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.food_items', 'id'));
            end;
            $$;

            -- 7. Realtime: perubahan isi kulkas dikirim ke anggota rumah (tetap disaring RLS) ---

            do $$
            begin
                if exists (select 1 from pg_publication where pubname = 'supabase_realtime')
                   and not exists (
                       select 1 from pg_publication_tables
                       where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'food_items'
                   ) then
                    alter publication supabase_realtime add table public.food_items;
                end if;
            end;
            $$;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            do $$
            begin
                if exists (
                    select 1 from pg_publication_tables
                    where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'food_items'
                ) then
                    alter publication supabase_realtime drop table public.food_items;
                end if;
            end;
            $$;

            revoke all on public.households, public.household_members, public.food_items from authenticated;

            drop policy if exists "Anggota dapat melihat rumahnya" on public.households;
            drop policy if exists "User login dapat membuat rumah" on public.households;
            drop policy if exists "Owner dapat mengubah rumah" on public.households;
            drop policy if exists "Owner dapat menghapus rumah" on public.households;
            drop policy if exists "Anggota dapat melihat sesama anggota" on public.household_members;
            drop policy if exists "Anggota dapat keluar, owner dapat mengeluarkan" on public.household_members;
            drop policy if exists "Anggota dapat melihat isi kulkas rumahnya" on public.food_items;
            drop policy if exists "Anggota dapat menambah bahan ke kulkas rumahnya" on public.food_items;
            drop policy if exists "Anggota dapat mengubah bahan di kulkas rumahnya" on public.food_items;
            drop policy if exists "Anggota dapat menghapus bahan di kulkas rumahnya" on public.food_items;

            drop trigger if exists households_add_owner on public.households;
            drop trigger if exists households_set_updated_at on public.households;
            drop trigger if exists food_items_set_updated_at on public.food_items;
            drop trigger if exists food_items_track_status on public.food_items;

            alter table public.households
                alter column created_by drop default,
                alter column invite_code drop default;
            alter table public.food_items
                alter column added_by drop default;

            drop function if exists private.add_household_owner();
            drop function if exists private.set_updated_at();
            drop function if exists private.track_food_item_status();
            drop function if exists private.generate_invite_code();
            drop function if exists private.is_household_member(bigint);
            drop function if exists private.is_household_owner(bigint);

            alter table public.households drop constraint if exists households_created_by_fkey;
            alter table public.household_members drop constraint if exists household_members_user_id_fkey;
            alter table public.food_items
                drop constraint if exists food_items_added_by_fkey,
                drop constraint if exists food_items_status_changed_by_fkey;
        SQL);
    }
};
