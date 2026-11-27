<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan Supabase advisor "extension_in_public": pg_trgm pindah dari schema public
 * (yang terbuka di Data API) ke schema extensions.
 *
 * Index idx_tkpi_nama_trgm tetap jalan: operator class-nya ikut pindah bersama extension.
 * word_similarity() di TkpiService tetap ditemukan karena search_path koneksi Laravel
 * sekarang public, extensions (config/database.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('create schema if not exists extensions');
            DB::statement('alter extension pg_trgm set schema extensions');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter extension pg_trgm set schema public');
        }
    }
};
