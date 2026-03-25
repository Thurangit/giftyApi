<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Réponses JSON homogènes pour les liens partageables indisponibles.
 * Le front affiche le libellé selon `unavailable_reason` (i18n), sans mentionner d'auteur.
 */
class LinkUnavailable
{
    public const CANCELLED = 'cancelled';

    public const DELETED = 'deleted';

    public const ALREADY_WON = 'already_won';

    /** Partie déjà jouée par ce participant (ex. MindMatch perdu ou terminé) — pas « déjà remporté ». */
    public const ALREADY_PLAYED = 'already_played';

    public const CLOSED = 'closed';

    public const EXPIRED = 'expired';

    public static function response(string $reason, int $http = 404): JsonResponse
    {
        $allowed = [self::CANCELLED, self::DELETED, self::ALREADY_WON, self::ALREADY_PLAYED, self::CLOSED, self::EXPIRED];
        if (! in_array($reason, $allowed, true)) {
            $reason = self::DELETED;
        }

        return response()->json([
            'success' => false,
            'unavailable_reason' => $reason,
        ], $http);
    }
}
