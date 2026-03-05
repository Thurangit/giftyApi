<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    /**
     * Créer un code promo
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50|unique:promo_codes,code',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'max_uses' => 'nullable|integer|min:1',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date|after:valid_from',
                'is_active' => 'boolean',
            ], [
                'code.required' => 'Le code est obligatoire.',
                'code.unique' => 'Ce code existe déjà.',
                'discount_percentage.required' => 'Le pourcentage de réduction est obligatoire.',
                'discount_percentage.min' => 'Le pourcentage doit être au moins 0.',
                'discount_percentage.max' => 'Le pourcentage ne peut pas dépasser 100.',
                'valid_until.after' => 'La date de fin doit être après la date de début.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $promoCode = PromoCode::create([
                'created_by' => $request->user()->id,
                'code' => strtoupper($request->code),
                'discount_percentage' => $request->discount_percentage,
                'max_uses' => $request->max_uses,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Code promo créé avec succès',
                'promo_code' => $promoCode
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du code promo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister les codes promo de l'utilisateur connecté
     */
    public function myPromoCodes(Request $request)
    {
        try {
            $user = $request->user();
            
            $promoCodes = PromoCode::where('created_by', $user->id)
                ->withCount('usages')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'promo_codes' => $promoCodes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des codes promo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un code promo spécifique
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $promoCode = PromoCode::where('id', $id)
                ->where('created_by', $user->id)
                ->with('usages.user')
                ->first();

            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code promo introuvable'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'promo_code' => $promoCode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du code promo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un code promo
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $promoCode = PromoCode::where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code promo introuvable'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $id,
                'discount_percentage' => 'sometimes|numeric|min:0|max:100',
                'max_uses' => 'nullable|integer|min:1',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date|after:valid_from',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $promoCode->update($request->only([
                'code', 'discount_percentage', 'max_uses', 'valid_from', 'valid_until', 'is_active'
            ]));

            if ($request->has('code')) {
                $promoCode->code = strtoupper($request->code);
                $promoCode->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Code promo mis à jour avec succès',
                'promo_code' => $promoCode->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un code promo
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $promoCode = PromoCode::where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code promo introuvable'
                ], 404);
            }

            $promoCode->delete();

            return response()->json([
                'success' => true,
                'message' => 'Code promo supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier la validité d'un code promo
     */
    public function validateCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code requis'
                ], 422);
            }

            $promoCode = PromoCode::where('code', strtoupper($request->code))->first();

            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Code promo invalide'
                ]);
            }

            $isValid = $promoCode->isValid();

            return response()->json([
                'success' => true,
                'valid' => $isValid,
                'promo_code' => $isValid ? [
                    'id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'discount_percentage' => $promoCode->discount_percentage,
                ] : null,
                'message' => $isValid ? 'Code promo valide' : 'Code promo expiré ou désactivé'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage()
            ], 500);
        }
    }
}

