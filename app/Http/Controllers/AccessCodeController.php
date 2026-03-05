<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Quiz;
use App\Models\Moment;
use App\Models\Challenge;
use Illuminate\Http\Request;

class AccessCodeController extends Controller
{
    /**
     * Rechercher un élément par code d'accès
     */
    public function searchByCode(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10'
        ]);

        $code = strtoupper(trim($validated['code']));

        // Déterminer le type selon le préfixe
        $type = null;
        $item = null;
        $redirectUrl = null;

        if (strpos($code, 'GF-') === 0) {
            // Cadeau
            $type = 'gift';
            $item = Gift::where('access_code', $code)->first();
            if ($item) {
                // Utiliser ref_two car c'est ce qui est utilisé dans les liens de partage
                $redirectUrl = '/Open/The/Gift/' . $item->ref_two;
            }
        } elseif (strpos($code, 'GZ-') === 0) {
            // Quiz
            $type = 'quiz';
            $item = Quiz::where('access_code', $code)->first();
            if ($item) {
                $redirectUrl = '/quiz/' . $item->unique_link;
            }
        } elseif (strpos($code, 'MO-') === 0) {
            // Moment
            $type = 'moment';
            $item = Moment::where('access_code', $code)->first();
            if ($item) {
                $redirectUrl = '/moment/' . $item->unique_link;
            }
        } elseif (strpos($code, '2N-') === 0) {
            // Challenge (À nous 2)
            $type = 'challenge';
            $item = Challenge::where('access_code', $code)->first();
            if ($item) {
                $redirectUrl = '/challenge/' . $item->unique_link . '/join';
            }
        } else {
            // Essayer de trouver dans toutes les tables (pour compatibilité avec les anciennes références)
            $item = Gift::where('ref_one', $code)
                ->orWhere('ref_two', $code)
                ->orWhere('ref_three', $code)
                ->first();
            
            if ($item) {
                $type = 'gift';
                // Utiliser ref_two si disponible, sinon ref_one
                $redirectUrl = '/Open/The/Gift/' . ($item->ref_two ? $item->ref_two : $item->ref_one);
            } else {
                // Essayer avec le lien unique des quiz
                $item = Quiz::where('unique_link', $code)->first();
                if ($item) {
                    $type = 'quiz';
                    $redirectUrl = '/quiz/' . $item->unique_link;
                } else {
                    // Essayer avec le lien unique des moments
                    $item = Moment::where('unique_link', $code)->first();
                    if ($item) {
                        $type = 'moment';
                        $redirectUrl = '/moment/' . $item->unique_link;
                    } else {
                        // Essayer avec le lien unique des challenges
                        $item = Challenge::where('unique_link', $code)->first();
                        if ($item) {
                            $type = 'challenge';
                            $redirectUrl = '/challenge/' . $item->unique_link . '/join';
                        }
                    }
                }
            }
        }

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Code non trouvé. Vérifiez que le code est correct.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'type' => $type,
            'item' => $item,
            'redirect_url' => $redirectUrl
        ]);
    }
}

