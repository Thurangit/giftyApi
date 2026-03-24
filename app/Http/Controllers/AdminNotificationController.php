<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushSubscription;
use App\Models\NotificationHistory;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Services\WebPushService;

class AdminNotificationController extends Controller
{
    /**
     * Envoyer une notification push
     */
    public function send(Request $request)
    {
        // Préparer les règles de validation selon le type de destinataire
        $rules = [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'recipient_type' => 'required|in:all,user',
            'url' => 'nullable|string',
            'requireInteraction' => 'boolean'
        ];

        // Ajouter la validation de user_id seulement si recipient_type est 'user'
        if ($request->recipient_type === 'user') {
            $rules['user_id'] = 'required|exists:users,id';
        } else {
            // Si recipient_type est 'all', user_id doit être null ou absent
            $rules['user_id'] = 'nullable';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Récupérer les abonnements selon le type de destinataire
            if ($request->recipient_type === 'all') {
                $subscriptions = PushSubscription::whereNotNull('user_id')->get();
            } else {
                $subscriptions = PushSubscription::where('user_id', $request->user_id)->get();
            }

            if ($subscriptions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun abonnement trouvé pour les destinataires sélectionnés'
                ], 404);
            }

            $sentCount = 0;
            $errors = [];

            // Préparer les données de la notification
            $notificationData = [
                'title' => $request->title,
                'body' => $request->body,
                'icon' => $request->icon ?: '/notification-icon.png',
                'badge' => '/notification-badge.png',
                'image' => $request->image,
                'tag' => 'eyamo-notification-' . time(),
                'requireInteraction' => $request->requireInteraction ?? false,
                'data' => [
                    'url' => $request->url ?: '/'
                ],
                'vibrate' => [200, 100, 200],
                'timestamp' => time() * 1000
            ];

            // Envoyer la notification à chaque abonnement
            foreach ($subscriptions as $subscription) {
                try {
                    // Préparer le payload pour l'API Web Push
                    $payload = json_encode($notificationData);
                    
                    // Envoyer via le service Web Push
                    $result = WebPushService::sendNotification($subscription, $notificationData);

                    if ($result['success']) {
                        $sentCount++;
                    } else {
                        $errorMsg = $result['error'] ?? "Status: " . ($result['status'] ?? 'unknown');
                        $errors[] = "Erreur pour l'endpoint {$subscription->endpoint}: {$errorMsg}";
                        
                        // Si l'endpoint est invalide (410 Gone), supprimer l'abonnement
                        if (isset($result['status']) && $result['status'] == 410) {
                            $subscription->delete();
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erreur pour l'endpoint {$subscription->endpoint}: " . $e->getMessage();
                }
            }

            // Sauvegarder dans l'historique
            $history = NotificationHistory::create([
                'title' => $request->title,
                'body' => $request->body,
                'icon' => $request->icon,
                'image' => $request->image,
                'recipient_type' => $request->recipient_type,
                'user_id' => $request->recipient_type === 'user' ? $request->user_id : null,
                'url' => $request->url,
                'require_interaction' => $request->requireInteraction ?? false,
                'sent_count' => $sentCount
            ]);

            // Créer des notifications pour chaque utilisateur
            if ($request->recipient_type === 'all') {
                // Récupérer tous les utilisateurs
                $users = User::all();
                foreach ($users as $user) {
                    UserNotification::create([
                        'user_id' => $user->id,
                        'notification_history_id' => $history->id,
                        'title' => $request->title,
                        'body' => $request->body,
                        'icon' => $request->icon,
                        'image' => $request->image,
                        'url' => $request->url,
                        'is_read' => false
                    ]);
                }
            } else {
                // Notification pour un utilisateur spécifique
                UserNotification::create([
                    'user_id' => $request->user_id,
                    'notification_history_id' => $history->id,
                    'title' => $request->title,
                    'body' => $request->body,
                    'icon' => $request->icon,
                    'image' => $request->image,
                    'url' => $request->url,
                    'is_read' => false
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Notification envoyée à {$sentCount} destinataire(s)",
                'sent_count' => $sentCount,
                'total_subscriptions' => $subscriptions->count(),
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer l'historique des notifications
     */
    public function history(Request $request)
    {
        try {
            $history = NotificationHistory::orderBy('created_at', 'desc')
                ->with('user:id,first_name,last_name,email')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'history' => $history->items(),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage()
            ], 500);
        }
    }
}

