<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RPC public.use_food_item: pakai sebagian bahan (mis. ¼ kotak susu) dalam satu UPDATE atomik.
 *
 * Dihitung di database supaya dua anggota yang menekan tombol bersamaan tidak saling menimpa
 * (baca-ubah-tulis dari Laravel bisa kehilangan salah satu pemakaian).
 * Bila sisa habis, bahan ditandai "dimasak" dan quantity dibiarkan (constraint quantity > 0).
 *
 * security invoker: berjalan sebagai user pemanggil, jadi RLS update food_items tetap berlaku.
 * Bahan rumah lain menghasilkan 0 baris, bukan error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            create or replace function public.use_food_item(p_item_id bigint, p_amount numeric)
            returns table (id bigint, quantity numeric, status text)
            language sql
            volatile
            security invoker
            set search_path = ''
            as $$
                update public.food_items f
                set quantity = case when f.quantity - p_amount > 0 then f.quantity - p_amount else f.quantity end,
                    status = case when f.quantity - p_amount > 0 then f.status else 'dimasak' end
                where f.id = p_item_id
                  and f.status = 'tersedia'
                  and p_amount > 0
                returning f.id, f.quantity, f.status::text;
            $$;

            revoke execute on function public.use_food_item(bigint, numeric) from public, anon;
            grant execute on function public.use_food_item(bigint, numeric) to authenticated;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('drop function if exists public.use_food_item(bigint, numeric);');
        }
    }
};
