<?php

namespace App\Support;

class WhatsAppNumber
{
    public static function normalize(?string $number): ?string
    {
        $number = trim($number ?? '');

        // Accept common display formatting, but never URL fragments or extensions.
        if (strlen($number) > 30 || ! preg_match('/\A\+?[0-9 ().-]+\z/', $number)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $number);

        // Convert an international dialing prefix into WhatsApp's digits-only format.
        if (str_starts_with($digits, '00') && ! str_starts_with($number, '+')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/\A[1-9][0-9]{6,14}\z/', $digits) ? $digits : null;
    }
}
