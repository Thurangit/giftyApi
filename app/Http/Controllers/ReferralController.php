<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReferralEarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReferralController extends Controller
{
    /**
     * Récupérer le code de parrainage de l'utilisateur connecté
     */
    public function getMyReferralCode(Request $request)
    {
        try {
            $user = $request->user();
            
            // Si l'utilisateur n'a pas encore de code de parrainage, en générer un
            if (!$user->referral_code) {
                $user->referral_code = \App\Helpers\CodeGenerator::generateReferralCode();
                $user->save();
            }

            return response()->json([
                'success' => true,
                'referral_code' => $user->referral_code,
                'referral_stats' => [
                    'total_referred' => $user->referredUsers()->count(),
                    'total_earnings' => $user->referralEarnings()->where('status', 'paid')->sum('earning_amount'),
                    'pending_earnings' => $user->referralEarnings()->where('status', 'pending')->sum('earning_amount'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du code de parrainage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques de parrainage de l'utilisateur
     */
    public function getMyReferralStats(Request $request)
    {
        try {
            $user = $request->user();
            
            $referredUsers = $user->referredUsers()
                ->select('id', 'first_name', 'last_name', 'email', 'phone', 'created_at')
                ->get();

            $earnings = $user->referralEarnings()
                ->with('referredUser:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_referred' => $referredUsers->count(),
                    'total_earnings' => $user->referralEarnings()->where('status', 'paid')->sum('earning_amount'),
                    'pending_earnings' => $user->referralEarnings()->where('status', 'pending')->sum('earning_amount'),
                    'cancelled_earnings' => $user->referralEarnings()->where('status', 'cancelled')->sum('earning_amount'),
                ],
                'referred_users' => $referredUsers,
                'earnings' => $earnings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer un code de parrainage pour l'utilisateur connecté (si il n'en a pas)
     */
    public function generateReferralCode(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->referral_code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà un code de parrainage'
                ], 400);
            }

            $user->referral_code = \App\Helpers\CodeGenerator::generateReferralCode();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Code de parrainage généré avec succès',
                'referral_code' => $user->referral_code
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du code: ' . $e->getMessage()
            ], 500);
        }
    }
}

