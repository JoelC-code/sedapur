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
        if (is_numeric($value)) {
            $number = (float) $value;
            if ($number >= 1000) {
                return (int) round($number);
            }
            return $number > 0 ? (int) round($number * 1000) : 0;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        if ($digits === null || $digits === '') {
            return 0;
        }

        $number = (int) $digits;
        return $number >= 1000 ? $number : $number * 1000;
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
        if (is_numeric($value)) {
            $number = (float) $value;
            if ($number >= 100000) {
                return (int) round($number);
            }
            if ($number >= 1000) {
                return (int) round($number * 1000);
            }
            return (int) round($number * 1000000);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        if ($digits === null || $digits === '') {
            return 0;
        }

        $number = (int) $digits;
        return $number >= 100000 ? $number : $number * 1000;
    }
}