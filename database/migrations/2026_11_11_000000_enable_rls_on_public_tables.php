<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Supabase membuka semua tabel di schema public lewat Data API (publishable key).
     * RLS tanpa policy menutup akses itu; Laravel tetap bisa karena login sebagai pemilik tabel.
     * shortcut: hanya tabel yang sudah ada saat migration ini jalan, tabel baru perlu ENABLE ROW LEVEL SECURITY di migration-nya sendiri.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select("select tablename from pg_tables where schemaname = 'public'");

        foreach ($tables as $table) {
            DB::statement(sprintf('alter table public.%s enable row level security', '"'.str_replace('"', '""', $table->tablename).'"'));
        }
    }

    public function down(): void
    {
        // Sengaja tidak mematikan RLS: membuka tabel ke publik bukan rollback yang aman.
    }
};
