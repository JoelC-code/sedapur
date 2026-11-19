<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bucket Supabase Storage untuk foto struk dan foto kulkas.
 *
 * Bucket privat: file hanya bisa dibuka lewat signed URL yang kedaluwarsa.
 * Path file: <household_id>/<uuid>.<ext>. RLS di storage.objects mengizinkan anggota rumah
 * membaca, mengunggah dan menghapus file di folder rumahnya sendiri saja.
 *
 * Hanya jalan di Postgres Supabase. Di SQLite (tes) migration ini dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
            values
                ('receipts', 'receipts', false, 5242880, array['image/jpeg', 'image/png', 'image/webp', 'image/heic']),
                ('fridge-photos', 'fridge-photos', false, 5242880, array['image/jpeg', 'image/png', 'image/webp', 'image/heic'])
            on conflict (id) do nothing;

            -- Apakah user yang login anggota rumah di folder pertama path ini (mis. "12/abc.jpg" -> rumah 12)?
            create or replace function private.can_access_household_folder(p_object_name text)
            returns boolean
            language sql
            stable
            set search_path = ''
            as $$
                select case
                    when (storage.foldername(p_object_name))[1] ~ '^[0-9]+$'
                        then private.is_household_member(((storage.foldername(p_object_name))[1])::bigint)
                    else false
                end;
            $$;

            revoke execute on function private.can_access_household_folder(text) from public, anon;
            grant execute on function private.can_access_household_folder(text) to authenticated;

            create policy "Anggota dapat melihat foto rumahnya"
                on storage.objects for select
                to authenticated
                using (bucket_id in ('receipts', 'fridge-photos') and private.can_access_household_folder(name));

            create policy "Anggota dapat mengunggah foto ke folder rumahnya"
                on storage.objects for insert
                to authenticated
                with check (bucket_id in ('receipts', 'fridge-photos') and private.can_access_household_folder(name));

            create policy "Anggota dapat menghapus foto rumahnya"
                on storage.objects for delete
                to authenticated
                using (bucket_id in ('receipts', 'fridge-photos') and private.can_access_household_folder(name));
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop policy if exists "Anggota dapat melihat foto rumahnya" on storage.objects;
            drop policy if exists "Anggota dapat mengunggah foto ke folder rumahnya" on storage.objects;
            drop policy if exists "Anggota dapat menghapus foto rumahnya" on storage.objects;
            drop function if exists private.can_access_household_folder(text);
            delete from storage.buckets where id in ('receipts', 'fridge-photos');
        SQL);
    }
};
