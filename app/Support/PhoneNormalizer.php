<?php

namespace App\Support;

/**
 * Normalisation des numéros pour comparaison (Cameroun : avec ou sans préfixe 237).
 */
final class PhoneNormalizer
{
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /** Derniers chiffres significatifs (ex. 9 pour CM sans indicatif). */
    public static function normalizeCm(?string $phone): string
    {
        $d = self::digits($phone);
        if (str_starts_with($d, '237') && strlen($d) > 9) {
            $d = substr($d, 3);
        }
        if (str_starts_with($d, '00237') && strlen($d) > 12) {
            $d = substr($d, 5);
        }

        return $d;
    }

    public static function same(?string $a, ?string $b): bool
    {
        $na = self::normalizeCm($a);
        $nb = self::normalizeCm($b);
        if ($na === '' || $nb === '') {
            return false;
        }

        return $na === $nb;
    }
}
