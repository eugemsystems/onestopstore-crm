<?php

namespace App\Notifications;

use AllowDynamicProperties;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

#[AllowDynamicProperties] class NewUserRegistered extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ((bool) getCachedSetting('send_emails')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Registration Pending Activation')
            ->greeting('Hello Admin,')
            ->line('A new user has registered and is pending verification.')
            ->line("Name: {$this->user->first_name} {$this->user->last_name}")
            ->line("Email: {$this->user->email}")
            ->action('Open Admin User', url('/app/users/'.$this->user->uuid))
            ->line('Please verify their documents and activate the account.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
