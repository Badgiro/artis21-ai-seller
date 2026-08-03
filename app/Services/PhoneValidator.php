<?php

namespace App\Services;

class PhoneValidator
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);

        if ($phone === '' || preg_match('/[^0-9+\s()\-]/u', $phone) === 1) {
            return null;
        }

        if (substr_count($phone, '+') > 1 || (str_contains($phone, '+') && ! str_starts_with($phone, '+'))) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === null || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return (str_starts_with($phone, '+') ? '+' : '').$digits;
    }

    public function isValid(string $phone): bool
    {
        return $this->normalize($phone) !== null;
    }
}
