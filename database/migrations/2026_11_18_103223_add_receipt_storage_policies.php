<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'

            -- Allow authenticated users to upload receipts
            -- only into their own UUID folder.

            CREATE POLICY "Users can upload their own receipts"
                ON storage.objects
                FOR INSERT
                TO authenticated
                WITH CHECK (
                    bucket_id = 'sedapur_receipt'
                    AND (storage.foldername(name))[1] = (select auth.uid())::text
                );

            -- Allow authenticated users to read
            -- only their own receipts.

            CREATE POLICY "Users can view their own receipts"
                ON storage.objects
                FOR SELECT
                TO authenticated
                USING (
                    bucket_id = 'sedapur_receipt'
                    AND (storage.foldername(name))[1] = (select auth.uid())::text
                );

        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'

            DROP POLICY IF EXISTS
                "Users can upload their own receipts"
                ON storage.objects;

            DROP POLICY IF EXISTS
                "Users can view their own receipts"
                ON storage.objects;

        SQL);
    }
};