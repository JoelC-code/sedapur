<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar belanja bersama per rumah (input manual; bahan dari resep menyusul).
 *
 * - Satuan sama dengan food_items_unit_check, supaya barang yang dicentang bisa langsung masuk kulkas.
 * - added_by diisi database (auth.uid()), bukan oleh client.
 * - RLS + policy: hanya anggota rumah yang bisa membaca/mengubah daftar belanja rumahnya.
 * - Grant per kolom ke authenticated (auto-expose di Supabase dimatikan).
 * - Masuk publication supabase_realtime agar centang sinkron ke semua anggota (tetap disaring RLS).
 *
 * Bagian SQL hanya jalan di Postgres. Di SQLite (tes) cukup tabelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('buah');
            $table->boolean('is_checked')->default(false);
            $table->uuid('added_by')->nullable()->index();
            $table->uuid('checked_by')->nullable()->index();
            $table->timestampTz('checked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Query paling sering: daftar belanja satu rumah, yang belum dicentang dulu.
            $table->index(['household_id', 'is_checked']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Constraint, foreign key ke Supabase Auth, default ---------------------------

            alter table public.shopping_list_items
                add constraint shopping_list_items_quantity_check check (quantity > 0),
                add constraint shopping_list_items_unit_check
                    check (unit in ('g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak')),
                add constraint shopping_list_items_added_by_fkey
                    foreign key (added_by) references auth.users (id) on delete set null,
                add constraint shopping_list_items_checked_by_fkey
                    foreign key (checked_by) references auth.users (id) on delete set null,
                alter column added_by set default auth.uid();

            -- 2. Trigger updated_at ----------------------------------------------------------

            create trigger shopping_list_items_set_updated_at
                before update on public.shopping_list_items
                for each row execute function private.set_updated_at();

            -- 3. Row Level Security ----------------------------------------------------------

            alter table public.shopping_list_items enable row level security;

            create policy "Anggota dapat melihat daftar belanja rumahnya"
                on public.shopping_list_items for select
                to authenticated
                using ((select private.is_household_member(household_id)));

            create policy "Anggota dapat menambah daftar belanja rumahnya"
                on public.shopping_list_items for insert
                to authenticated
                with check ((select private.is_household_member(household_id)) and added_by = (select auth.uid()));

            create policy "Anggota dapat mengubah daftar belanja rumahnya"
                on public.shopping_list_items for update
                to authenticated
                using ((select private.is_household_member(household_id)))
                with check ((select private.is_household_member(household_id)));

            create policy "Anggota dapat menghapus daftar belanja rumahnya"
                on public.shopping_list_items for delete
                to authenticated
                using ((select private.is_household_member(household_id)));

            -- 4. Grant (per kolom: added_by, household_id dan timestamp tidak bisa diubah client) --

            revoke all on public.shopping_list_items from anon;

            grant select, delete on public.shopping_list_items to authenticated;
            grant insert (household_id, name, quantity, unit) on public.shopping_list_items to authenticated;
            grant update (name, quantity, unit, is_checked, checked_by, checked_at) on public.shopping_list_items to authenticated;

            do $$
            begin
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.shopping_list_items', 'id'));
            end;
            $$;

            -- 5. Realtime --------------------------------------------------------------------

            do $$
            begin
                if exists (select 1 from pg_publication where pubname = 'supabase_realtime')
                   and not exists (
                       select 1 from pg_publication_tables
                       where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'shopping_list_items'
                   ) then
                    alter publication supabase_realtime add table public.shopping_list_items;
                end if;
            end;
            $$;
        SQL);
    }

    public function down(): void
    {
        // Policy, trigger, grant dan keanggotaan publication ikut terhapus bersama tabelnya.
        Schema::dropIfExists('shopping_list_items');
    }
};
