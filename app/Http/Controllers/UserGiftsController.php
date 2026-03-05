<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\User;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserGiftsController extends Controller
{
    /**
     * Récupérer les cadeaux envoyés par l'utilisateur (par email uniquement)
     */
    public function getSentGifts(Request $request)
    {
        try {
            $email = $request->query('email');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir une adresse email'
                ], 400);
            }

            // Filtrer uniquement par email
            $gifts = Gift::where('email', strtolower($email))
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedGifts = $gifts->map(function ($gift) {
                // Normaliser le statut Delivery vers received pour compatibilité
                $normalizedStatus = ($gift->status === 'Delivery') ? 'received' : $gift->status;
                return [
                    'id' => $gift->id,
                    'ref' => $gift->ref_one,
                    'name' => $gift->name,
                    'amount' => $gift->amount,
                    'receiver' => $gift->receiver,
                    'receiver_name' => $gift->other_one,
                    'message' => $gift->message,
                    'image' => $gift->image,
                    'status' => $normalizedStatus,
                    'is_received' => $normalizedStatus === 'received' || $normalizedStatus === 'completed',
                    'can_withdraw' => $normalizedStatus === 'pending' || $normalizedStatus === 'active',
                    'created_at' => $gift->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'gifts' => $formattedGifts,
                'total' => $gifts->count(),
                'total_amount' => $gifts->sum('amount'),
                'pending_count' => $gifts->whereIn('status', ['pending', 'active'])->count(),
                'received_count' => $gifts->whereIn('status', ['received', 'completed', 'Delivery'])->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des cadeaux: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les cadeaux reçus par l'utilisateur (par téléphone)
     */
    public function getReceivedGifts(Request $request)
    {
        try {
            $phone = $request->query('phone');

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir un numéro de téléphone'
                ], 400);
            }

            $gifts = Gift::where('receiver', 'like', '%' . $phone . '%')
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedGifts = $gifts->map(function ($gift) {
                // Normaliser le statut Delivery vers received pour compatibilité
                $normalizedStatus = ($gift->status === 'Delivery') ? 'received' : $gift->status;
                return [
                    'id' => $gift->id,
                    'ref' => $gift->ref_one,
                    'sender_name' => $gift->name,
                    'amount' => $gift->amount,
                    'message' => $gift->message,
                    'image' => $gift->image,
                    'status' => $normalizedStatus,
                    'is_claimed' => $normalizedStatus === 'received' || $normalizedStatus === 'completed',
                    'can_claim' => $normalizedStatus === 'pending' || $normalizedStatus === 'active',
                    'created_at' => $gift->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'gifts' => $formattedGifts,
                'total' => $gifts->count(),
                'total_amount' => $gifts->sum('amount'),
                'claimed_count' => $gifts->whereIn('status', ['received', 'completed', 'Delivery'])->count(),
                'pending_count' => $gifts->whereIn('status', ['pending', 'active'])->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des cadeaux: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les détails d'un cadeau
     */
    public function getGiftDetails($ref)
    {
        try {
            $gift = Gift::where('ref_one', $ref)->first();

            if (!$gift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cadeau non trouvé'
                ], 404);
            }

            // Normaliser le statut Delivery vers received pour compatibilité
            $normalizedStatus = ($gift->status === 'Delivery') ? 'received' : $gift->status;
            
            return response()->json([
                'success' => true,
                'gift' => [
                    'id' => $gift->id,
                    'ref' => $gift->ref_one,
                    'name' => $gift->name,
                    'amount' => $gift->amount,
                    'sender' => $gift->sender,
                    'sender_operator' => $gift->sender_opertor,
                    'receiver' => $gift->receiver,
                    'receiver_operator' => $gift->receiver_opertor,
                    'receiver_name' => $gift->other_one,
                    'message' => $gift->message,
                    'image' => $gift->image,
                    'email' => $gift->email,
                    'status' => $normalizedStatus,
                    'created_at' => $gift->created_at,
                    'updated_at' => $gift->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler un cadeau non réclamé et récupérer l'argent dans le wallet
     */
    public function cancelGift(Request $request, $ref)
    {
        try {
            DB::beginTransaction();

            $gift = Gift::where('ref_one', $ref)->first();

            if (!$gift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cadeau non trouvé'
                ], 404);
            }

            // Vérifier que le cadeau n'a pas été réclamé
            if (in_array($gift->status, ['received', 'completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce cadeau ne peut pas être annulé'
                ], 400);
            }

            // Trouver l'utilisateur par email
            $user = null;
            if ($gift->email) {
                $user = User::where('email', strtolower($gift->email))->first();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé. Impossible de rembourser le montant.'
                ], 404);
            }

            // Ajouter l'argent au wallet
            WalletController::addToWallet(
                $user->id,
                $gift->amount,
                'gift',
                $gift->id,
                $gift->ref_one,
                'Remboursement - Annulation du cadeau ' . $gift->ref_one
            );

            // Mettre à jour le statut du cadeau
            $gift->update(['status' => 'cancelled']);

            DB::commit();

            // Récupérer le nouveau solde
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Cadeau annulé avec succès. Le montant de ' . number_format($gift->amount) . ' XAF a été ajouté à votre wallet.',
                'amount' => $gift->amount,
                'new_balance' => $user->balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}

