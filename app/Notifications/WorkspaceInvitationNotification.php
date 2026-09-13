<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    public function __construct(public Invitation $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspaceName = $this->invitation->workspace->name;

        return (new MailMessage)
            ->subject("Приглашение в бюджет «{$workspaceName}»")
            ->greeting('Здравствуйте!')
            ->line("Вас пригласили в семейный бюджет «{$workspaceName}».")
            ->action('Открыть приглашение', url('/invitations/'.$this->invitation->token))
            ->line('Если вы не ожидали это письмо, просто проигнорируйте его.')
            ->salutation('— Monetka');
    }
}
