<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat aktivitas kulkas (halaman /riwayat), dicatat oleh database sendiri.
 *
 * - Trigger AFTER INSERT/UPDATE di food_items menulis satu baris per aksi: ditambah, dipakai,
 *   diubah, dimasak, dibuang. Jalur apa pun yang mengubah bahan (form, RPC use_food_item,
 *   Edge Function, dashboard) otomatis tercatat; Laravel tidak bisa lupa mencatat.
 * - Client hanya boleh membaca (grant select). Tidak ada grant insert/update/delete, jadi riwayat
 *   tidak bisa dipalsukan atau dihapus lewat Data API. Yang menulis hanya fungsi trigger
 *   (security definer, di schema private).
 * - RLS: anggota hanya melihat riwayat rumahnya.
 * - Bahan yang sudah ada diisi ulang (backfill) agar riwayat tidak kosong.
 *
 * Bagian SQL hanya jalan di Postgres. Di SQLite (tes) cukup tabelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_item_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('action', 10);
            $table->string('item_name', 120);
            $table->string('description', 200)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Query halaman riwayat: satu rumah, terbaru dulu.
            $table->index(['household_id', 'created_at']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Constraint dan foreign key ke Supabase Auth ---------------------------------

            alter table public.activity_logs
                add constraint activity_logs_action_check
                    check (action in ('ditambah', 'dipakai', 'diubah', 'dimasak', 'dibuang')),
                add constraint activity_logs_actor_id_fkey
                    foreign key (actor_id) references auth.users (id) on delete set null;

            -- 2. Helper format jumlah: 0.50 kotak -> "0,5 kotak" --------------------------------

            create or replace function private.format_quantity(p_quantity numeric, p_unit text)
            returns text
            language sql
            immutable
            set search_path = ''
            as $$
                select replace(trim_scale(p_quantity)::text, '.', ',') || ' ' || p_unit;
            $$;

            -- 3. Fungsi trigger: tulis satu baris riwayat per perubahan bahan ------------------

            -- security definer: client tidak punya grant insert ke activity_logs,
            -- jadi hanya fungsi ini (milik postgres) yang bisa menulis riwayat.
            create or replace function private.log_food_item_activity()
            returns trigger
            language plpgsql
            security definer
            set search_path = ''
            as $$
            declare
                v_action text;
                v_note text;
                v_days_late int;
                v_value numeric;
            begin
                if tg_op = 'INSERT' then
                    v_action := 'ditambah';
                    v_note := case new.input_source
                                  when 'scan_struk' then 'Scan struk'
                                  when 'scan_kulkas' then 'Scan kulkas'
                                  else 'Input manual'
                              end || ' · ' || private.format_quantity(new.quantity, new.unit);

                -- Abaikan update yang tidak mengubah data bahan (misalnya hanya updated_at).
                elsif (new.name, new.category, new.quantity, new.unit, new.weight_grams, new.price,
                       new.purchased_at, new.expires_at, new.status)
                      is not distinct from
                      (old.name, old.category, old.quantity, old.unit, old.weight_grams, old.price,
                       old.purchased_at, old.expires_at, old.status) then
                    return null;

                elsif new.status is distinct from old.status and new.status in ('dimasak', 'dibuang') then
                    v_action := new.status;

                    if new.status = 'dimasak' then
                        v_note := 'Bahan sudah habis';
                    else
                        v_note := 'Sisa ' || private.format_quantity(new.quantity, new.unit);
                        v_days_late := (now() at time zone 'Asia/Jakarta')::date - new.expires_at;
                        if v_days_late > 0 then
                            v_note := v_note || ' · lewat ' || v_days_late || ' hari';
                        end if;
                        -- Nilai yang terbuang = harga x porsi yang tersisa.
                        v_value := round(new.price * new.quantity / nullif(new.initial_quantity, 0));
                        if v_value > 0 then
                            v_note := v_note || ' · Rp ' || replace(to_char(v_value, 'FM999,999,999'), ',', '.');
                        end if;
                    end if;

                elsif new.status is distinct from old.status then
                    v_action := 'diubah';
                    v_note := 'Dikembalikan ke kulkas';

                -- Hanya jumlah yang turun: pemakaian sebagian (RPC use_food_item).
                elsif new.quantity < old.quantity
                      and (new.name, new.category, new.unit, new.weight_grams, new.price, new.purchased_at, new.expires_at)
                          is not distinct from
                          (old.name, old.category, old.unit, old.weight_grams, old.price, old.purchased_at, old.expires_at) then
                    v_action := 'dipakai';
                    v_note := 'Dipakai ' || private.format_quantity(old.quantity - new.quantity, new.unit)
                              || ' · sisa ' || private.format_quantity(new.quantity, new.unit);

                else
                    v_action := 'diubah';
                    v_note := concat_ws(' · ',
                        case when new.name is distinct from old.name
                             then 'Nama ' || old.name || ' → ' || new.name end,
                        case when (new.quantity, new.unit) is distinct from (old.quantity, old.unit)
                             then 'Jumlah ' || private.format_quantity(old.quantity, old.unit)
                                  || ' → ' || private.format_quantity(new.quantity, new.unit) end,
                        case when new.expires_at is distinct from old.expires_at
                             then 'Kedaluwarsa ' || coalesce(to_char(old.expires_at, 'DD/MM'), '-')
                                  || ' → ' || coalesce(to_char(new.expires_at, 'DD/MM'), '-') end
                    );
                    if v_note = '' then
                        v_note := 'Detail bahan diperbarui';
                    end if;
                end if;

                insert into public.activity_logs (household_id, food_item_id, actor_id, action, item_name, description)
                values (new.household_id, new.id, (select auth.uid()), v_action, new.name, left(v_note, 200));

                return null;
            end;
            $$;

            -- Keduanya hanya dipakai di dalam trigger (berjalan sebagai pemilik fungsi),
            -- jadi tidak ada role client yang perlu execute.
            revoke execute on function private.log_food_item_activity() from public, anon, authenticated;
            revoke execute on function private.format_quantity(numeric, text) from public, anon, authenticated;

            create trigger food_items_log_activity
                after insert or update on public.food_items
                for each row execute function private.log_food_item_activity();

            -- 4. Row Level Security: baca saja, hanya anggota rumah ----------------------------

            alter table public.activity_logs enable row level security;

            create policy "Anggota dapat melihat riwayat rumahnya"
                on public.activity_logs for select
                to authenticated
                using ((select private.is_household_member(household_id)));

            revoke all on public.activity_logs from anon, authenticated;
            grant select on public.activity_logs to authenticated;

            -- 5. Backfill: bahan yang sudah ada sebelum trigger dibuat --------------------------

            insert into public.activity_logs (household_id, food_item_id, actor_id, action, item_name, description, created_at)
            select f.household_id, f.id, f.added_by, 'ditambah', f.name,
                   case f.input_source
                       when 'scan_struk' then 'Scan struk'
                       when 'scan_kulkas' then 'Scan kulkas'
                       else 'Input manual'
                   end || ' · ' || private.format_quantity(f.initial_quantity, f.unit),
                   f.created_at
            from public.food_items f;

            insert into public.activity_logs (household_id, food_item_id, actor_id, action, item_name, description, created_at)
            select f.household_id, f.id, f.status_changed_by, f.status, f.name,
                   case when f.status = 'dimasak' then 'Bahan sudah habis'
                        else 'Sisa ' || private.format_quantity(f.quantity, f.unit) end,
                   f.status_changed_at
            from public.food_items f
            where f.status in ('dimasak', 'dibuang') and f.status_changed_at is not null;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                drop trigger if exists food_items_log_activity on public.food_items;
                drop function if exists private.log_food_item_activity();
                drop function if exists private.format_quantity(numeric, text);
            SQL);
        }

        Schema::dropIfExists('activity_logs');
    }
};
