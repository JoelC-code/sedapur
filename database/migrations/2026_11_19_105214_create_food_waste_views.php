<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Analitik food waste (halaman /food-waste dan kartu dashboard) sebagai view di database.
 *
 * - food_waste_monthly: per rumah per bulan (WIB): jumlah bahan dimasak, dibuang, dan rupiah terbuang.
 * - food_waste_items: per rumah per bulan per nama bahan: berapa kali dibuang dan rupiahnya.
 * - Rupiah terbuang = harga beli x porsi yang tersisa saat dibuang (quantity / initial_quantity),
 *   jadi susu yang dibuang setengah hanya dihitung setengah harga.
 * - security_invoker = true: view dijalankan dengan hak user yang memanggil, sehingga RLS
 *   food_items tetap berlaku. Tanpa ini view berjalan sebagai pemiliknya (postgres) dan
 *   setiap user bisa melihat angka semua rumah.
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
            create or replace view public.food_waste_monthly
            with (security_invoker = true)
            as
            select
                f.household_id,
                date_trunc('month', f.status_changed_at at time zone 'Asia/Jakarta')::date as month,
                count(*) filter (where f.status = 'dimasak') as cooked_count,
                count(*) filter (where f.status = 'dibuang') as wasted_count,
                coalesce(sum(round(f.price * f.quantity / f.initial_quantity)) filter (where f.status = 'dibuang'), 0)::bigint as wasted_value
            from public.food_items f
            where f.status in ('dimasak', 'dibuang') and f.status_changed_at is not null
            group by f.household_id, month;

            create or replace view public.food_waste_items
            with (security_invoker = true)
            as
            select
                f.household_id,
                date_trunc('month', f.status_changed_at at time zone 'Asia/Jakarta')::date as month,
                min(f.name) as name,
                count(*) as wasted_count,
                coalesce(sum(round(f.price * f.quantity / f.initial_quantity)), 0)::bigint as wasted_value
            from public.food_items f
            where f.status = 'dibuang' and f.status_changed_at is not null
            group by f.household_id, month, lower(f.name);

            revoke all on public.food_waste_monthly, public.food_waste_items from anon, authenticated;
            grant select on public.food_waste_monthly, public.food_waste_items to authenticated;

            -- Analitik membaca bahan per rumah berdasarkan waktu dimasak/dibuang.
            create index if not exists food_items_household_status_changed_idx
                on public.food_items (household_id, status_changed_at)
                where status in ('dimasak', 'dibuang');

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop view if exists public.food_waste_items;
            drop view if exists public.food_waste_monthly;
            drop index if exists public.food_items_household_status_changed_idx;
        SQL);
    }
};
