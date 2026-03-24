<?php

namespace App\Helpers;

use App\Models\Gift;
use App\Models\Quiz;
use App\Models\Moment;
use App\Models\Challenge;
use App\Models\User;

class CodeGenerator
{
    /**
     * Génère un code d'accès unique selon le type
     * Format: 2 lettres + tiret + 7 chiffres
     * 
     * @param string $type 'gift', 'quiz', 'moment', 'challenge'
     * @return string
     */
    public static function generateAccessCode($type)
    {
        $prefix = self::getPrefix($type);
        
        do {
            // Générer 7 chiffres aléatoires
            $numbers = str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            $code = $prefix . '-' . $numbers;
            
            // Vérifier l'unicité selon le type
            $exists = self::codeExists($code, $type);
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Récupère le préfixe selon le type
     */
    private static function getPrefix($type)
    {
        $prefixes = [
            'gift' => 'GF',
            'quiz' => 'GZ',
            'moment' => 'MO',
            'challenge' => '2N'
        ];
        
        return $prefixes[$type] ?? 'XX';
    }
    
    /**
     * Vérifie si un code existe déjà dans la base de données
     */
    private static function codeExists($code, $type)
    {
        switch ($type) {
            case 'gift':
                return Gift::where('access_code', $code)->exists();
            case 'quiz':
                return Quiz::where('access_code', $code)->exists();
            case 'moment':
                return Moment::where('access_code', $code)->exists();
            case 'challenge':
                return Challenge::where('access_code', $code)->exists();
            case 'referral':
                return User::where('referral_code', $code)->exists();
            case 'eyamo_user':
                return User::where('eyamo_code', $code)->exists();
            default:
                return false;
        }
    }

    /**
     * Code utilisateur Eyamo : E + 2 lettres A-Z + tiret + 7 chiffres (ex: EKZ-1234567).
     */
    public static function generateEyamoUserCode(): string
    {
        do {
            $a = chr(65 + random_int(0, 25));
            $b = chr(65 + random_int(0, 25));
            $numbers = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            $code = 'E' . $a . $b . '-' . $numbers;
            $exists = self::codeExists($code, 'eyamo_user');
        } while ($exists);

        return $code;
    }

    /**
     * Génère un code de parrainage unique
     * Format: REF- suivi de 8 caractères alphanumériques
     */
    public static function generateReferralCode()
    {
        do {
            // Générer 8 caractères aléatoires (majuscules et chiffres)
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = 'REF-' . substr(str_shuffle(str_repeat($characters, 8)), 0, 8);
            
            // Vérifier l'unicité
            $exists = self::codeExists($code, 'referral');
        } while ($exists);
        
        return $code;
    }
}

