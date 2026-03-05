# Configuration Backend - Notifications Push

## ✅ Fichiers créés

### 1. Migrations
- `2025_01_21_000001_create_push_subscriptions_table.php` - Table pour stocker les abonnements push
- `2025_01_21_000002_create_notification_history_table.php` - Table pour l'historique des notifications

### 2. Modèles
- `app/Models/PushSubscription.php` - Modèle pour les abonnements
- `app/Models/NotificationHistory.php` - Modèle pour l'historique

### 3. Contrôleurs
- `app/Http/Controllers/NotificationController.php` - Gestion des abonnements (subscribe/unsubscribe)
- `app/Http/Controllers/AdminNotificationController.php` - Envoi et historique des notifications (admin)

### 4. Services
- `app/Services/WebPushService.php` - Service pour l'envoi des notifications

### 5. Routes
Les routes ont été ajoutées dans `routes/api.php` :
- `POST /api/notifications/subscribe` - S'abonner (auth:sanctum)
- `POST /api/notifications/unsubscribe` - Se désabonner (auth:sanctum)
- `POST /api/admin/notifications/send` - Envoyer une notification (auth:sanctum + admin)
- `GET /api/admin/notifications/history` - Historique (auth:sanctum + admin)

## 🚀 Installation

### 1. Exécuter les migrations

```bash
cd giftyApi
php artisan migrate
```

### 2. Vérifier les routes

```bash
php artisan route:list | grep notification
```

Vous devriez voir les 4 routes listées ci-dessus.

### 3. Tester les endpoints

#### S'abonner (nécessite un token d'authentification)
```bash
curl -X POST http://192.168.18.237:8000/api/notifications/subscribe \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "endpoint": "https://fcm.googleapis.com/fcm/send/...",
    "keys": {
      "p256dh": "...",
      "auth": "..."
    }
  }'
```

#### Envoyer une notification (admin)
```bash
curl -X POST http://192.168.18.237:8000/api/admin/notifications/send \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Notification",
    "body": "Ceci est un test",
    "icon": "/notification-icon.png",
    "recipient_type": "all"
  }'
```

## ⚠️ Notes importantes

1. **L'envoi de notifications push nécessite l'utilisation d'une bibliothèque spécialisée** comme `laravel-notification-channels/webpush` pour gérer correctement le cryptage avec les clés VAPID.

2. **Pour l'instant, le service WebPushService envoie les données en JSON simple**. Pour un système de production, vous devriez :
   - Installer `composer require laravel-notification-channels/webpush`
   - Utiliser cette bibliothèque pour l'envoi crypté des notifications

3. **Les clés VAPID** doivent être configurées dans le `.env` :
   ```
   VAPID_PUBLIC_KEY=...
   VAPID_PRIVATE_KEY=...
   ```

4. **Les endpoints push** sont fournis par les navigateurs (Chrome, Firefox, etc.) et sont uniques pour chaque abonnement.

## 🔧 Améliorations futures

Pour un système de production complet, considérez :
- Utiliser `laravel-notification-channels/webpush` pour l'envoi crypté
- Ajouter une queue pour l'envoi asynchrone des notifications
- Implémenter un système de retry pour les échecs
- Ajouter des métriques et monitoring

