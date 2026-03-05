<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDuplicateAttemptNotification extends Notification
{
    use Queueable;

    protected $contactType; // 'email' ou 'phone'
    protected $contactValue; // La valeur de l'email ou du téléphone
    protected $context; // 'creation' ou 'modification'

    /**
     * Create a new notification instance.
     */
    public function __construct(string $contactType, string $contactValue, string $context = 'creation')
    {
        $this->contactType = $contactType;
        $this->contactValue = $contactValue;
        $this->context = $context;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $contactLabel = $this->contactType === 'email' ? 'adresse email' : 'numéro de téléphone';
        $contextLabel = $this->context === 'creation' ? 'créé' : 'modifié';
        
        $message = (new MailMessage)
            ->subject('Tentative d\'utilisation de votre ' . $contactLabel)
            ->greeting('Bonjour ' . $notifiable->first_name . ',')
            ->line('Nous vous informons qu\'une tentative de ' . $contextLabel . ' un compte avec votre ' . $contactLabel . ' (' . $this->contactValue . ') a été détectée sur notre plateforme.')
            ->line('Si vous n\'êtes pas à l\'origine de cette action, nous vous recommandons de :')
            ->line('• Vérifier la sécurité de votre compte')
            ->line('• Changer votre mot de passe si nécessaire')
            ->line('• Nous contacter si vous pensez qu\'il s\'agit d\'une tentative frauduleuse')
            ->line('Si vous êtes à l\'origine de cette action, vous pouvez ignorer ce message.')
            ->line('Merci de votre vigilance.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_type' => $this->contactType,
            'contact_value' => $this->contactValue,
            'context' => $this->context,
        ];
    }
}
