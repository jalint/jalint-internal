<?php

namespace App\Utils;

class AmountToWordsUtil
{
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /**
     * Konversi angka ke kalimat terbilang rupiah.
     *
     * * @param float|int|string|null $amount
     */
    public static function toWords($amount): string
    {
        if ($amount === null || $amount == 0) {
            return 'Nol rupiah';
        }

        // Menggunakan round untuk simulasi RoundingMode.HALF_UP
        $value = (int) round($amount);

        $result = self::convertToWords($value);

        return self::capitalize(trim($result)).' rupiah';
    }

    private static function convertToWords(int $n): string
    {
        if ($n < 12) {
            return self::SATUAN[$n];
        } elseif ($n < 20) {
            return self::SATUAN[$n - 10].' belas';
        } elseif ($n < 100) {
            return self::SATUAN[(int) ($n / 10)].' puluh '.self::convertToWords($n % 10);
        } elseif ($n < 200) {
            return 'seratus '.self::convertToWords($n - 100);
        } elseif ($n < 1000) {
            return self::SATUAN[(int) ($n / 100)].' ratus '.self::convertToWords($n % 100);
        } elseif ($n < 2000) {
            return 'seribu '.self::convertToWords($n - 1000);
        } elseif ($n < 1000000) {
            return self::convertToWords((int) ($n / 1000)).' ribu '.self::convertToWords($n % 1000);
        } elseif ($n < 1000000000) {
            return self::convertToWords((int) ($n / 1000000)).' juta '.self::convertToWords($n % 1000000);
        } elseif ($n < 1000000000000) {
            return self::convertToWords((int) ($n / 1000000000)).' miliar '.self::convertToWords($n % 1000000000);
        } else {
            return self::convertToWords((int) ($n / 1000000000000)).' triliun '.self::convertToWords($n % 1000000000000);
        }
    }

    private static function capitalize(string $text): string
    {
        if (empty($text)) {
            return $text;
        }
        // Membersihkan multiple spaces seperti regex di Java
        $text = preg_replace('/\s+/', ' ', trim($text));

        return ucfirst($text);
    }
}
