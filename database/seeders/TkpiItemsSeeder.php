<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class TkpiItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            // [kode, nama_bahan, kelompok, kalori, protein, karbo, lemak, bdd]
            ['AR001', 'Nasi Putih Konvensional', 'Serealia', 180.0, 3.0, 39.8, 0.3, 100],
            ['AR002', 'Beras Merah Mentah', 'Serealia', 352.0, 7.5, 76.2, 1.9, 100],
            ['DY001', 'Daging Ayam Kampung', 'Daging', 246.0, 18.2, 0.0, 18.7, 58],
            ['DY002', 'Daging Sapi Murni', 'Daging', 201.0, 18.8, 0.0, 14.0, 100],
            ['IK001', 'Ikan Lele Segar', 'Ikan', 84.0, 14.8, 0.0, 2.3, 78],
            ['IK002', 'Ikan Bandeng Segar', 'Ikan', 129.0, 20.0, 0.0, 4.8, 80],
            ['OL001', 'Tempe Kedelai Murni', 'Olahan Kedelai', 201.0, 20.8, 13.5, 8.8, 100],
            ['OL002', 'Tahu Putih Segar', 'Olahan Kedelai', 80.0, 10.9, 0.8, 4.7, 100],
            ['SY001', 'Kangkung Segar', 'Sayuran', 28.0, 3.4, 3.9, 0.7, 70],
            ['SY002', 'Bayam Segar', 'Sayuran', 16.0, 0.9, 2.9, 0.4, 71],
            ['SY003', 'Daun Singkong', 'Sayuran', 50.0, 6.2, 7.1, 1.1, 87],
            ['BU001', 'Pisang Ambon', 'Buah', 108.0, 1.0, 24.3, 0.8, 75],
            ['BU002', 'Alpukat Segar', 'Buah', 85.0, 0.9, 7.7, 6.5, 61],
            ['SP001', 'Minyak Kelapa Sawit', 'Minyak', 902.0, 0.0, 0.0, 100.0, 100],
            ['SP002', 'Santan Kelapa Perah', 'Serba-serbi', 123.0, 2.0, 5.6, 10.0, 100],
        ];

        foreach ($items as $item) {
            DB::table('tkpi_items')->updateOrInsert(
                ['kode_bahan' => $item[0]],
                [
                    'nama_bahan' => $item[1],
                    'kelompok' => $item[2],
                    'kalori_100g' => $item[3],
                    'protein_100g' => $item[4],
                    'karbohidrat_100g' => $item[5],
                    'lemak_100g' => $item[6],
                    'bdd' => $item[7],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
