<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Foto struk sekarang disimpan di bucket "receipts" (folder per rumah, lihat
 * 2026_11_18_100154_create_storage_buckets_for_photos). Policy bucket lama "sedapur_receipt"
 * (folder per user) dicabut supaya tidak ada dua aturan akses untuk fitur yang sama.
 *
 * Bucket-nya sendiri dikosongkan dan dihapus lewat dashboard: Supabase tidak mengizinkan
 * menghapus isi storage.objects langsung dengan SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop policy if exists "Users can upload their own receipts" on storage.objects;
            drop policy if exists "Users can view their own receipts" on storage.objects;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            create policy "Users can upload their own receipts"
                on storage.objects for insert to authenticated
                with check (bucket_id = 'sedapur_receipt' and (storage.foldername(name))[1] = (select auth.uid())::text);

            create policy "Users can view their own receipts"
                on storage.objects for select to authenticated
                using (bucket_id = 'sedapur_receipt' and (storage.foldername(name))[1] = (select auth.uid())::text);
        SQL);
    }
};
