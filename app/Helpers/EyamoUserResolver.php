<?php

namespace App\Helpers;

use App\Models\User;

class EyamoUserResolver
{
    /**
     * Résout un utilisateur Eyamo à partir du code E##-#######, email ou téléphone.
     */
    public static function resolve(string $raw): ?User
    {
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }

        if (preg_match('/^E([A-Za-z]{2})-(\d{7})$/', $trim, $m)) {
            $code = 'E' . strtoupper($m[1]) . '-' . $m[2];

            return User::where('eyamo_code', $code)->first();
        }

        if (filter_var($trim, FILTER_VALIDATE_EMAIL)) {
            return User::whereRaw('LOWER(email) = ?', [strtolower($trim)])->first();
        }

        $digits = preg_replace('/\D/', '', $trim);
        if (strlen($digits) >= 9) {
            $u = User::where('phone', $digits)->first();
            if ($u) {
                return $u;
            }
            if (str_starts_with($digits, '237')) {
                return User::where('phone', substr($digits, 3))->first();
            }

            return User::where('phone', '237' . $digits)->first();
        }

        return null;
    }
}
