<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah awal tiap bahan, supaya pemakaian sebagian (susu diminum ¼ kotak, bawang dipakai 100 g
 * dari 1 kg) bisa dicatat dan uang terbuang dihitung dari sisa, bukan dari harga penuh.
 *
 * initial_quantity diisi otomatis oleh trigger dari quantity saat insert dan tidak bisa diubah
 * user (tidak ada grant update), jadi dasar hitungan waste tidak bisa dimanipulasi.
 * unit dibatasi ke daftar satuan baku agar hasil scan Gemini dan input manual seragam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->decimal('initial_quantity', 10, 2)->nullable()->after('quantity');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            update public.food_items set initial_quantity = quantity where initial_quantity is null;

            create or replace function private.set_food_item_initial_quantity()
            returns trigger
            language plpgsql
            set search_path = ''
            as $$
            begin
                new.initial_quantity := new.quantity;
                return new;
            end;
            $$;

            revoke execute on function private.set_food_item_initial_quantity() from public, anon, authenticated;

            create trigger food_items_set_initial_quantity
                before insert on public.food_items
                for each row execute function private.set_food_item_initial_quantity();

            update public.food_items set unit = 'buah'
                where unit not in ('g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak');

            alter table public.food_items
                alter column initial_quantity set not null,
                add constraint food_items_initial_quantity_check check (initial_quantity > 0),
                add constraint food_items_unit_check
                    check (unit in ('g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak'));
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                drop trigger if exists food_items_set_initial_quantity on public.food_items;
                drop function if exists private.set_food_item_initial_quantity();
                alter table public.food_items drop constraint if exists food_items_unit_check;
            SQL);
        }

        Schema::table('food_items', function (Blueprint $table) {
            $table->dropColumn('initial_quantity');
        });
    }
};
