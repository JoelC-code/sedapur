<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resep per rumah (public.recipes) beserta daftar bahannya (public.recipe_ingredients).
 *
 * - Resep bisa dibuat AI dari isi kulkas (is_ai_generated = true) lalu disimpan user (is_saved = true).
 * - Semua operasi user berjalan lewat Supabase Data API dengan access token user (RLS aktif).
 * - Satuan bahan mengikuti food_items_unit_check agar bahan yang belum ada di kulkas bisa langsung
 *   dimasukkan ke shopping_list_items tanpa gagal constraint.
 *
 * Bagian SQL hanya jalan di Postgres. Di SQLite (tes) cukup tabelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->uuid('created_by')->nullable()->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('steps');
            $table->unsignedSmallInteger('servings')->default(2);
            $table->unsignedSmallInteger('cook_minutes')->default(20);
            $table->decimal('calories_per_serving', 7, 1)->default(0);
            $table->decimal('protein_per_serving', 6, 1)->default(0);
            $table->decimal('carbs_per_serving', 6, 1)->default(0);
            $table->decimal('fat_per_serving', 6, 1)->default(0);
            $table->boolean('is_ai_generated')->default(true);
            $table->boolean('is_saved')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['household_id', 'is_saved', 'created_at']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('amount_text', 50);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('buah');
            $table->decimal('grams', 8, 1)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index(['recipe_id', 'sort_order']);
            $table->index('household_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Constraint, foreign key ke Supabase Auth, default ---------------------------

            alter table public.recipes
                add constraint recipes_servings_check check (servings > 0),
                add constraint recipes_cook_minutes_check check (cook_minutes > 0),
                add constraint recipes_nutrients_check
                    check (calories_per_serving >= 0 and protein_per_serving >= 0 and carbs_per_serving >= 0 and fat_per_serving >= 0),
                add constraint recipes_created_by_fkey
                    foreign key (created_by) references auth.users (id) on delete set null,
                alter column created_by set default auth.uid();

            alter table public.recipe_ingredients
                add constraint recipe_ingredients_quantity_check check (quantity > 0),
                add constraint recipe_ingredients_grams_check check (grams is null or grams > 0),
                add constraint recipe_ingredients_unit_check
                    check (unit in ('g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak'));

            -- 2. Trigger updated_at ----------------------------------------------------------

            create trigger recipes_set_updated_at
                before update on public.recipes
                for each row execute function private.set_updated_at();

            -- 3. Row Level Security ----------------------------------------------------------

            alter table public.recipes enable row level security;

            create policy "Anggota dapat melihat resep rumahnya"
                on public.recipes for select
                to authenticated
                using ((select private.is_household_member(household_id)));

            create policy "Anggota dapat menambah resep rumahnya"
                on public.recipes for insert
                to authenticated
                with check ((select private.is_household_member(household_id)) and created_by = (select auth.uid()));

            create policy "Anggota dapat mengubah resep rumahnya"
                on public.recipes for update
                to authenticated
                using ((select private.is_household_member(household_id)))
                with check ((select private.is_household_member(household_id)));

            create policy "Anggota dapat menghapus resep rumahnya"
                on public.recipes for delete
                to authenticated
                using ((select private.is_household_member(household_id)));

            alter table public.recipe_ingredients enable row level security;

            create policy "Anggota dapat melihat bahan resep rumahnya"
                on public.recipe_ingredients for select
                to authenticated
                using ((select private.is_household_member(household_id)));

            create policy "Anggota dapat menambah bahan resep rumahnya"
                on public.recipe_ingredients for insert
                to authenticated
                with check (
                    (select private.is_household_member(household_id))
                    and exists (
                        select 1 from public.recipes r
                        where r.id = recipe_id and r.household_id = recipe_ingredients.household_id
                    )
                );

            create policy "Anggota dapat menghapus bahan resep rumahnya"
                on public.recipe_ingredients for delete
                to authenticated
                using ((select private.is_household_member(household_id)));

            -- 4. Grant per kolom ke authenticated --------------------------------------------

            revoke all on public.recipes from anon, authenticated;

            grant select, delete on public.recipes to authenticated;
            grant insert (
                household_id, name, description, steps, servings, cook_minutes,
                calories_per_serving, protein_per_serving, carbs_per_serving, fat_per_serving,
                is_ai_generated, is_saved
            ) on public.recipes to authenticated;
            grant update (is_saved) on public.recipes to authenticated;

            revoke all on public.recipe_ingredients from anon, authenticated;

            grant select, delete on public.recipe_ingredients to authenticated;
            grant insert (recipe_id, household_id, name, amount_text, quantity, unit, grams, sort_order)
                on public.recipe_ingredients to authenticated;

            do $$
            begin
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.recipes', 'id'));
                execute format('grant usage on sequence %s to authenticated', pg_get_serial_sequence('public.recipe_ingredients', 'id'));
            end;
            $$;

            notify pgrst, 'reload schema';
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
