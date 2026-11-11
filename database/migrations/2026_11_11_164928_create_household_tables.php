<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti Sedapur: rumah/kos, anggotanya, dan isi kulkas.
 *
 * Kolom user (created_by, user_id, added_by) berisi id dari Supabase Auth (auth.users, uuid).
 * Foreign key ke auth.users, RLS, policy dan grant ada di migration secure_household_tables,
 * karena hanya bisa jalan di Postgres Supabase (tes memakai SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('invite_code', 12)->unique();
            $table->uuid('created_by')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('household_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->string('role', 10)->default('member');
            $table->timestampTz('joined_at')->useCurrent();

            $table->unique(['household_id', 'user_id']);
        });

        Schema::create('food_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 20)->default('lainnya');
            $table->string('emoji', 16)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('buah');
            $table->decimal('weight_grams', 10, 2)->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('input_source', 20)->default('manual');
            $table->string('status', 10)->default('tersedia');
            $table->uuid('added_by')->nullable()->index();
            $table->timestampTz('status_changed_at')->nullable();
            $table->uuid('status_changed_by')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Query paling sering: isi kulkas satu rumah, diurutkan dari yang paling cepat kedaluwarsa.
            $table->index(['household_id', 'status', 'expires_at']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Nilai yang boleh disimpan, sama dengan yang dipakai halaman (DemoData::CATEGORIES, filter /kulkas).
        DB::statement(<<<'SQL'
            alter table public.household_members
                add constraint household_members_role_check check (role in ('owner', 'member'))
        SQL);

        DB::statement(<<<'SQL'
            alter table public.food_items
                add constraint food_items_category_check check (category in ('sayur', 'buah', 'daging', 'ikan', 'susu_telur', 'bumbu', 'kemasan', 'lainnya')),
                add constraint food_items_input_source_check check (input_source in ('scan_struk', 'scan_kulkas', 'manual')),
                add constraint food_items_status_check check (status in ('tersedia', 'dimasak', 'dibuang')),
                add constraint food_items_quantity_check check (quantity > 0),
                add constraint food_items_weight_check check (weight_grams is null or weight_grams > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('food_items');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};
