<?php

namespace App\Http\Controllers;

use App\Models\Moment;
use App\Models\MomentItem;
use App\Models\MomentAttempt;
use App\Helpers\CodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MomentController extends Controller
{
    /**
     * Créer un nouveau moment
     */
    public function createMoment(Request $request)
    {
        try {
            $validated = $request->validate([
                'creator_name' => 'required|string|max:255',
                'creator_email' => 'nullable|email|max:255',
                'total_moments' => 'required|integer|in:3,4,5',
                'best_moment_order' => 'required|integer|min:1',
                'amount' => 'required|integer|min:1',
                'opening_message' => 'nullable|string|max:1000',
                'moments' => 'required|array|min:3|max:5',
                'moments.*.description' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
                'errors' => $e->errors()
            ], 422);
        }

        // Vérifier que best_moment_order est valide
        if ($validated['best_moment_order'] < 1 || $validated['best_moment_order'] > $validated['total_moments']) {
            return response()->json([
                'success' => false,
                'message' => 'L\'ordre du meilleur moment doit être entre 1 et ' . $validated['total_moments']
            ], 400);
        }

        // Vérifier que le nombre de moments correspond
        if (count($validated['moments']) !== $validated['total_moments']) {
            return response()->json([
                'success' => false,
                'message' => 'Le nombre de moments doit correspondre au total sélectionné'
            ], 400);
        }

        // Générer un lien unique
        $uniqueLink = 'moment-' . Str::random(32);

        // Créer le moment
        $moment = Moment::create([
            'creator_name' => $validated['creator_name'],
            'creator_email' => $validated['creator_email'] ?? null,
            'unique_link' => $uniqueLink,
            'access_code' => CodeGenerator::generateAccessCode('moment'),
            'total_moments' => $validated['total_moments'],
            'best_moment_order' => $validated['best_moment_order'],
            'amount' => $validated['amount'],
            'opening_message' => $validated['opening_message'] ?? null,
            'status' => 'active'
        ]);

        // Créer les moments
        foreach ($validated['moments'] as $index => $momentData) {
            MomentItem::create([
                'moment_id' => $moment->id,
                'moment_description' => $momentData['description'],
                'moment_order' => $index + 1
            ]);
        }

        // Utiliser l'URL du frontend depuis la requête ou une valeur par défaut
        $frontendUrl = $request->header('Origin') ?: 'http://localhost:3000';
        $shareLink = rtrim($frontendUrl, '/') . '/moment/' . $uniqueLink;

        return response()->json([
            'success' => true,
            'moment' => $moment->load('items'),
            'share_link' => $shareLink
        ], 201);
    }

    /**
     * Récupérer un moment par son lien unique
     */
    public function getMoment($link)
    {
        $moment = Moment::where('unique_link', $link)
            ->where('status', 'active')
            ->with(['items'])
            ->first();

        if (!$moment) {
            return response()->json([
                'success' => false,
                'message' => 'Moment non trouvé ou inactif'
            ], 404);
        }

        // Randomiser l'ordre des moments à chaque fois pour éviter que le meilleur soit toujours au même endroit
        // On crée un mapping entre l'ordre original et le nouvel ordre aléatoire
        $items = $moment->items->shuffle()->values();
        
        // Créer un nouveau tableau avec les items mélangés mais en gardant l'ordre original pour la comparaison
        $shuffledItems = $items->map(function ($item, $newIndex) {
            return [
                'id' => $item->id,
                'moment_id' => $item->moment_id,
                'moment_description' => $item->moment_description,
                'moment_order' => $item->moment_order, // Garder l'ordre original pour la comparaison
                'display_order' => $newIndex + 1, // Nouvel ordre d'affichage
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        });

        // Retourner les données avec l'ordre mélangé
        $momentData = $moment->toArray();
        $momentData['items'] = $shuffledItems->toArray();
        $momentData['best_moment_order'] = $moment->best_moment_order; // L'ordre original du meilleur moment

        return response()->json([
            'success' => true,
            'moment' => $momentData
        ]);
    }

    /**
     * Récupérer les informations de partage d'un moment (sans vérifier le statut)
     */
    public function getMomentShareInfo($link)
    {
        $moment = Moment::where('unique_link', $link)
            ->select('id', 'unique_link', 'access_code', 'creator_name', 'creator_email', 'amount', 'status', 'created_at')
            ->first();

        if (!$moment) {
            return response()->json([
                'success' => false,
                'message' => 'Moment non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'moment' => $moment
        ]);
    }

    /**
     * Vérifier si un participant peut accéder au moment
     */
    public function checkAccess(Request $request, $link)
    {
        $validated = $request->validate([
            'phone' => 'nullable|string'
        ]);

        $moment = Moment::where('unique_link', $link)
            ->where('status', 'active')
            ->first();

        if (!$moment) {
            return response()->json([
                'success' => false,
                'message' => 'Moment non trouvé ou inactif'
            ], 404);
        }

        // Si un numéro de téléphone est requis (participant_phone n'est pas null)
        if ($moment->participant_phone) {
            // Si aucun numéro n'est fourni, refuser l'accès
            if (!$validated['phone']) {
                return response()->json([
                    'success' => false,
                    'can_access' => false,
                    'message' => 'Ce moment nécessite un numéro de téléphone'
                ], 403);
            }

            // Vérifier que le numéro correspond
            if ($moment->participant_phone !== $validated['phone']) {
                return response()->json([
                    'success' => false,
                    'can_access' => false,
                    'message' => 'Vous n\'êtes pas autorisé à participer à ce moment'
                ], 403);
            }
        }

        // Si un numéro est fourni, vérifier si la personne a déjà participé
        if ($validated['phone']) {
            $existingAttempt = MomentAttempt::where('moment_id', $moment->id)
                ->where('participant_phone', $validated['phone'])
                ->first();

            if ($existingAttempt) {
                return response()->json([
                    'success' => false,
                    'can_access' => false,
                    'already_participated' => true,
                    'message' => 'Vous avez déjà participé à ce moment'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'can_access' => true,
            'requires_phone' => !is_null($moment->participant_phone)
        ]);
    }

    /**
     * Soumettre la réponse d'un moment
     */
    public function submitMoment(Request $request, $link)
    {
        try {
            $validated = $request->validate([
                'participant_name' => 'required|string|max:255',
                'participant_phone' => 'nullable|string|max:20',
                'selected_moment_order' => 'required|integer|min:1'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
                'errors' => $e->errors()
            ], 422);
        }

        $moment = Moment::where('unique_link', $link)
            ->where('status', 'active')
            ->first();

        if (!$moment) {
            return response()->json([
                'success' => false,
                'message' => 'Moment non trouvé ou inactif'
            ], 404);
        }

        // Si un numéro de téléphone est requis pour ce moment
        if ($moment->participant_phone) {
            // Vérifier qu'un numéro a été fourni
            if (!$validated['participant_phone']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce moment nécessite un numéro de téléphone'
                ], 400);
            }

            // Vérifier que le numéro correspond
            if ($moment->participant_phone !== $validated['participant_phone']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à participer à ce moment'
                ], 403);
            }
        }

        // Vérifier si la personne a déjà participé (seulement si un numéro est fourni)
        if ($validated['participant_phone']) {
            $existingAttempt = MomentAttempt::where('moment_id', $moment->id)
                ->where('participant_phone', $validated['participant_phone'])
                ->first();

            if ($existingAttempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà participé à ce moment'
                ], 403);
            }
        }

        // Vérifier que selected_moment_order est valide
        if ($validated['selected_moment_order'] < 1 || $validated['selected_moment_order'] > $moment->total_moments) {
            return response()->json([
                'success' => false,
                'message' => 'Ordre du moment invalide'
            ], 400);
        }

        // Vérifier si c'est le bon moment
        $hasWon = $validated['selected_moment_order'] === $moment->best_moment_order;
        $wonAmount = $hasWon ? $moment->amount : 0;

        // Créer la tentative
        $attempt = MomentAttempt::create([
            'moment_id' => $moment->id,
            'participant_name' => $validated['participant_name'],
            'participant_phone' => $validated['participant_phone'],
            'selected_moment_order' => $validated['selected_moment_order'],
            'has_won' => $hasWon,
            'won_amount' => $wonAmount,
            'status' => 'completed'
        ]);

        // Marquer le moment comme complété (peu importe succès ou échec)
        $moment->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'attempt_id' => $attempt->id,
            'result' => [
                'has_won' => $hasWon,
                'won_amount' => $wonAmount,
                'selected_moment_order' => $validated['selected_moment_order'],
                'best_moment_order' => $moment->best_moment_order
            ]
        ]);
    }

    /**
     * Récupérer le résultat d'une tentative
     */
    public function getMomentResult($attemptId)
    {
        try {
            $attempt = MomentAttempt::with(['moment.items'])
                ->find($attemptId);

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tentative non trouvée'
                ], 404);
            }

            if (!$attempt->moment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Moment associé non trouvé'
                ], 404);
            }

            // Trouver le meilleur moment
            $bestMoment = $attempt->moment->items->where('moment_order', $attempt->moment->best_moment_order)->first();

            return response()->json([
                'success' => true,
                'result' => [
                    'participant_name' => $attempt->participant_name,
                    'has_won' => (bool) $attempt->has_won,
                    'won_amount' => (float) ($attempt->won_amount ?? 0),
                    'selected_moment_order' => (int) $attempt->selected_moment_order,
                    'best_moment_order' => (int) $attempt->moment->best_moment_order,
                    'best_moment_description' => $bestMoment ? $bestMoment->moment_description : null,
                    'selected_moment_description' => $attempt->moment->items->where('moment_order', $attempt->selected_moment_order)->first()?->moment_description
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getMomentResult: ' . $e->getMessage(), [
                'attempt_id' => $attemptId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du résultat: ' . $e->getMessage()
            ], 500);
        }
    }
}

