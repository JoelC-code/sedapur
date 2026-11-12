<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengisi tabel tkpi_items dengan Tabel Komposisi Pangan Indonesia (nilai per 100 g).
 *
 * Sumber nilai gizi: Tabel Komposisi Pangan Indonesia, Kementerian Kesehatan RI (https://www.panganku.org).
 * Hasil scraping dan pembersihan oleh tim riset Jatim Melaju 2026 (UPNVJT):
 * https://github.com/intanypurbasari/food-nutrition, berkas data_clean/tkpi_clean.csv
 * (nilai terukur, bukan hasil imputasi). Lisensi MIT repo itu hanya untuk kodenya; nilai TKPI
 * tetap tunduk pada ketentuan Kemenkes, dipakai di sini untuk keperluan akademik non-komersial.
 * Berkas itu dipangkas menjadi database/data/tkpi.csv (hanya kolom yang dipakai tabel ini).
 *
 * Dataset tidak memuat BDD (berat dapat dimakan), jadi kolom bdd memakai default 100.
 * Dua nilai kosong di sumber (lemak "Salak, segar", protein "Melase") disimpan sebagai 0.
 * Seeder ini mengganti seluruh isi tabel, aman dijalankan berulang kali.
 */
class TkpiItemsSeeder extends Seeder
{
    private const CSV_PATH = 'data/tkpi.csv';

    private const CHUNK_SIZE = 200;

    public function run(): void
    {
        $rows = $this->readCsv(database_path(self::CSV_PATH));
        $now = now();

        DB::transaction(function () use ($rows, $now): void {
            DB::table('tkpi_items')->delete();

            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                DB::table('tkpi_items')->insert(array_map(fn (array $row): array => [
                    ...$row,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        $this->command?->info(count($rows).' bahan TKPI dimasukkan.');
    }

    /**
     * @return list<array{kode_bahan: string, nama_bahan: string, kelompok: string, kalori_100g: float, protein_100g: float, karbohidrat_100g: float, lemak_100g: float}>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("File TKPI tidak ditemukan: {$path}");
        }

        $header = fgetcsv($handle, escape: '');
        $rows = [];

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $row = array_combine($header, $values);

            $rows[] = [
                'kode_bahan' => $row['kode_bahan'],
                'nama_bahan' => $row['nama_bahan'],
                'kelompok' => $row['kelompok'],
                'kalori_100g' => (float) $row['kalori_100g'],
                'protein_100g' => (float) $row['protein_100g'],
                'karbohidrat_100g' => (float) $row['karbohidrat_100g'],
                'lemak_100g' => (float) $row['lemak_100g'],
            ];
        }

        fclose($handle);

        return $rows;
    }
}
