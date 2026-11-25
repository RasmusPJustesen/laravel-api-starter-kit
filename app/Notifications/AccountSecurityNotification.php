<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSecurityNotification extends Notification
{
    public function __construct(private string $action) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Account security notification'))
            ->line(__('A security setting on your account has changed: :action.', ['action' => __($this->action)]))
            ->line(__('If you did not make this change, contact the application support team.'));
    }
}
