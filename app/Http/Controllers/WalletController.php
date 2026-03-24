<?php

namespace App\Http\Controllers;

use App\Helpers\EyamoUserResolver;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Récupérer l'historique des transactions du wallet de l'utilisateur
     */
    public function getTransactions(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $transactions = WalletTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $totalDeposits = WalletTransaction::where('user_id', $user->id)
                ->where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount');

            $totalWithdrawals = WalletTransaction::where('user_id', $user->id)
                ->where('type', 'withdrawal')
                ->where('status', 'completed')
                ->sum('amount');

            $totalRefunds = WalletTransaction::where('user_id', $user->id)
                ->where('type', 'refund')
                ->where('status', 'completed')
                ->sum('amount');

            return response()->json([
                'success' => true,
                'balance' => $user->balance,
                'transactions' => $transactions,
                'stats' => [
                    'total_deposits' => $totalDeposits,
                    'total_withdrawals' => $totalWithdrawals,
                    'total_refunds' => $totalRefunds,
                    'net_balance' => $totalDeposits + $totalRefunds - $totalWithdrawals,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retirer de l'argent du wallet
     */
    public function withdraw(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $op = $request->input('operator');
            $phoneForDesc = '';

            if ($op === 'eyamo') {
                $request->validate([
                    'amount' => 'required|numeric|min:0.01',
                    'operator' => 'required|in:eyamo',
                    'eyamo_identifier' => 'required|string|max:255',
                ]);
                $resolved = EyamoUserResolver::resolve($request->eyamo_identifier);
                if (! $resolved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Compte Eyamo introuvable pour cet identifiant',
                    ], 422);
                }
                $phoneForDesc = preg_replace('/\D/', '', (string) $resolved->phone);
            } else {
                $request->validate([
                    'amount' => 'required|numeric|min:0.01',
                    'phone_number' => 'required|string|regex:/^[0-9]{9,15}$/',
                    'operator' => 'required|in:orange,mtn',
                ]);
                $phoneForDesc = preg_replace('/\D/', '', (string) $request->phone_number);
            }

            $amount = $request->amount;

            if ($user->balance < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant'
                ], 400);
            }

            $descOp = $op === 'eyamo' ? 'Eyamo' : $request->operator;

            // Créer la transaction de retrait
            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'source_type' => 'manual',
                'amount' => $amount,
                'description' => 'Retrait vers ' . $descOp . ' (' . $phoneForDesc . ')',
                'status' => 'pending',
            ]);

            // TODO: Intégrer avec l'API de paiement (Campay ou autre)
            // Pour l'instant, on simule le retrait
            // Dans un vrai système, vous appelleriez l'API de paiement ici

            // Mettre à jour le solde de l'utilisateur
            $user->balance -= $amount;
            $user->save();

            // Marquer la transaction comme complétée
            $transaction->status = 'completed';
            $transaction->save();

            return response()->json([
                'success' => true,
                'message' => 'Retrait effectué avec succès',
                'transaction' => $transaction,
                'new_balance' => $user->balance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter de l'argent au wallet (pour annulation de cadeau/quiz/moment)
     */
    public static function addToWallet($userId, $amount, $sourceType, $sourceId, $sourceRef, $description = null)
    {
        try {
            DB::beginTransaction();

            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            // Créer la transaction
            $transaction = WalletTransaction::create([
                'user_id' => $userId,
                'type' => 'refund',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_ref' => $sourceRef,
                'amount' => $amount,
                'description' => $description ?? 'Remboursement de ' . $sourceType,
                'status' => 'completed',
            ]);

            // Mettre à jour le solde
            $user->balance += $amount;
            $user->save();

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

