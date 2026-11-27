<?php

namespace App\Support;

class ReceiptPriceNormalizer
{
    public static function unit(mixed $value): string
    {
        $unit = mb_strtolower(trim((string) $value));

        $aliases = [
            'pcs' => 'buah',
            'pc' => 'buah',
            'piece' => 'buah',
            'pack' => 'bungkus',
            'packs' => 'bungkus',
            'packaging' => 'bungkus',
            'kgm' => 'kg',
            'kilogram' => 'kg',
            'kilogramm' => 'kg',
            'liter' => 'l',
            'milliliter' => 'ml',
        ];

        $unit = $aliases[$unit] ?? $unit;

        return in_array($unit, [
            'g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat',
            'bungkus', 'botol', 'kaleng', 'kotak',
        ], true) ? $unit : 'buah';
    }

    public static function unitPrice(mixed $value): int
    {
        return self::parseRupiah($value);
    }

    public static function lineTotal(mixed $quantity, mixed $unitPrice): int
    {
        $quantity = is_numeric($quantity) ? (float) $quantity : 0;
        $unitPrice = self::unitPrice($unitPrice);

        return $quantity > 0 && $unitPrice > 0
            ? (int) round($quantity * $unitPrice)
            : 0;
    }

    public static function receiptTotal(mixed $value): int
    {
        return self::parseRupiah($value);
    }

    private static function parseRupiah(mixed $value): int
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return 0;
        }

        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            if ($value <= 0) {
                return 0;
            }

            // Gemini kadang membaca pemisah ribuan "48.400" sebagai angka desimal JSON 48.4 atau 39.0.
            return $value < 1000
                ? (int) round($value * 1000)
                : (int) round($value);
        }

        $raw = trim((string) $value);
        $raw = preg_replace('/^(?:rp\.?\s*)/iu', '', $raw) ?? $raw;
        $raw = trim($raw);

        if ($raw === '' || str_starts_with($raw, '-')) {
            return 0;
        }

        // Format ribuan lengkap Indonesia/internasional, mis. "48.400", "48.400,00", "1.250.000".
        if (preg_match('/^(\d{1,3}(?:[.,]\d{3})+)(?:[.,]\d{1,2})?$/', $raw, $matches)) {
            return (int) preg_replace('/[^0-9]/', '', $matches[1]);
        }

        // Angka bulat murni dalam string, mis. "48400" atau "200".
        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        // Angka desimal dalam string, mis. "48400.00" atau "48.4".
        if (preg_match('/^(\d+)[.,](\d{1,2})$/', $raw, $matches)) {
            $number = (float) ($matches[1].'.'.$matches[2]);

            if ($number <= 0) {
                return 0;
            }

            if ($number >= 1000 || (int) $matches[2] === 0) {
                return (int) round($number);
            }

            return (int) round($number * 1000);
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        return $digits === null || $digits === '' ? 0 : (int) $digits;
    }
}
