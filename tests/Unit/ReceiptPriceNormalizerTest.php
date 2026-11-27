<?php

use App\Support\ReceiptPriceNormalizer;

it('normalizes receipt totals without multiplying valid rupiah values into millions', function (mixed $input, int $expected) {
    expect(ReceiptPriceNormalizer::receiptTotal($input))->toBe($expected);
})->with([
    'integer under 100k' => [48400, 48400],
    'string integer under 100k' => ['48400', 48400],
    'formatted rupiah' => ['Rp 48.400', 48400],
    'formatted rupiah with cents' => ['Rp 48.400,00', 48400],
    'millions formatted' => ['1.250.000', 1250000],
    'json float from thousands dot' => [48.4, 48400],
    'json float whole thousands' => [39.0, 39000],
    'zero' => [0, 0],
    'null' => [null, 0],
]);

it('normalizes unit prices including small rupiah amounts and formatted strings', function (mixed $input, int $expected) {
    expect(ReceiptPriceNormalizer::unitPrice($input))->toBe($expected);
})->with([
    'small integer like plastic bag' => [200, 200],
    'small string integer' => ['500', 500],
    'standard integer' => [39000, 39000],
    'standard string' => ['5000', 5000],
    'formatted string' => ['Rp 5.000', 5000],
    'float from thousands dot' => [12.5, 12500],
    'decimal string over 1000' => ['48400.00', 48400],
    'negative' => [-1000, 0],
    'empty' => ['', 0],
]);
