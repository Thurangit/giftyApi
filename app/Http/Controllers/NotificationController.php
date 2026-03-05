<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * S'abonner aux notifications push
     */
    public function subscribe(Request $request)
    {
        // Le frontend envoie { subscription: { endpoint, keys: { p256dh, auth } } }
        // Ou { endpoint, keys: { p256dh, auth } }
        $subscriptionData = $request->has('subscription')
            ? $request->input('subscription')
            : $request->all();

        $validator = Validator::make($subscriptionData, [
            'endpoint' => 'required|url',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Vérifier si l'abonnement existe déjà
            $subscription = PushSubscription::where('endpoint', $subscriptionData['endpoint'])->first();

            if ($subscription) {
                // Mettre à jour l'abonnement existant
                $subscription->update([
                    'user_id' => $user ? $user->id : null,
                    'public_key' => $subscriptionData['keys']['p256dh'],
                    'auth_token' => $subscriptionData['keys']['auth'],
                    'user_agent' => $request->userAgent()
                ]);
            } else {
                // Créer un nouvel abonnement
                $subscription = PushSubscription::create([
                    'user_id' => $user ? $user->id : null,
                    'endpoint' => $subscriptionData['endpoint'],
                    'public_key' => $subscriptionData['keys']['p256dh'],
                    'auth_token' => $subscriptionData['keys']['auth'],
                    'user_agent' => $request->userAgent()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Abonnement réussi',
                'subscription_id' => $subscription->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'abonnement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Se désabonner des notifications push
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint requis',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = PushSubscription::where('endpoint', $request->endpoint)->first();

            if ($subscription) {
                $subscription->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Désabonnement réussi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du désabonnement: ' . $e->getMessage()
            ], 500);
        }
    }
}

