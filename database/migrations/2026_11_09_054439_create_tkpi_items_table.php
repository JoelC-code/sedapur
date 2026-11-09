<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tkpi_items', function (Blueprint $table) {
            $table->id();
            $table->string('kode_bahan', 20)->nullable();
            $table->string('nama_bahan')->index();
            $table->string('kelompok')->nullable();
            $table->decimal('kalori_100g', 8, 2)->default(0);
            $table->decimal('protein_100g', 8, 2)->default(0);
            $table->decimal('karbohidrat_100g', 8, 2)->default(0);
            $table->decimal('lemak_100g', 8, 2)->default(0);
            $table->decimal('bdd', 5, 2)->default(100); // Berat Dapat Dimakan (%)
            $table->timestamps();
        });

        if(DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            DB::statement('CREATE INDEX idx_tkpi_nama_trgm ON tkpi_items USING gin (nama_bahan gin_trgm_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tkpi_items');
    }
};
