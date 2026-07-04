<?php

namespace App\Support;

class PhoneFormatter
{
    public static function indonesia(?string $phone): ?string
    {
        $raw = trim((string) ($phone ?? ''));

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (! is_string($digits) || $digits === '') {
            return $raw;
        }

        if (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '62')) {
            return $raw;
        }

        $nationalNumber = substr($digits, 2);

        if (str_starts_with($nationalNumber, '0')) {
            $nationalNumber = substr($nationalNumber, 1);
        }

        if ($nationalNumber === '') {
            return '+62';
        }

        if (strlen($nationalNumber) <= 3) {
            return '+62 '.$nationalNumber;
        }

        $prefix = substr($nationalNumber, 0, 3);
        $remaining = substr($nationalNumber, 3);

        if ($remaining === '') {
            return '+62 '.$prefix;
        }

        return '+62 '.$prefix.'-'.implode('-', str_split($remaining, 4));
    }
}
