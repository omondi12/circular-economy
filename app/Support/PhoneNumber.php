<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalizes 0712345678, 712345678, and 254712345678 to the stored
     * 254712345678 form used for Nawiri payouts.
     */
    public static function normalizeKenyan(mixed $value): string
    {
        $phone = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($phone, '0')) {
            return '254'.substr($phone, 1);
        }
        if (strlen($phone) === 9 && in_array($phone[0] ?? '', ['7', '1'], true)) {
            return '254'.$phone;
        }

        return $phone;
    }
}
