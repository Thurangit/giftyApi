<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    /**
     * Envoyer une notification push via l'API Web Push
     */
    public static function sendNotification($subscription, $payload)
    {
        try {
            // Vérifier que les clés VAPID sont configurées
            $publicKey = config('webpush.vapid.public_key');
            $privateKey = config('webpush.vapid.private_key');
            $subject = config('webpush.vapid.subject');

            if (!$publicKey || !$privateKey) {
                Log::error('VAPID keys not configured');
                return [
                    'success' => false,
                    'error' => 'VAPID keys not configured. Please run: php artisan webpush:vapid'
                ];
            }

            // Configuration VAPID
            $auth = [
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ]
            ];

            $webPush = new WebPush($auth);

            // Créer l'objet Subscription
            $pushSubscription = Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
            ]);

            // Envoyer la notification
            $result = $webPush->sendOneNotification(
                $pushSubscription,
                json_encode($payload),
                ['TTL' => 5000]
            );

            if ($result->isSuccess()) {
                return ['success' => true];
            } else {
                Log::error('WebPush send failed', [
                    'reason' => $result->getReason(),
                    'expired' => $result->isSubscriptionExpired()
                ]);

                return [
                    'success' => false,
                    'error' => $result->getReason(),
                    'status' => $result->getResponse() ? $result->getResponse()->getStatusCode() : null,
                    'expired' => $result->isSubscriptionExpired()
                ];
            }
        } catch (\Exception $e) {
            Log::error('WebPush error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

