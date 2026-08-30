<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\BuildsCustomerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceReactivated extends Notification implements ShouldQueue
{
    use BuildsCustomerMail, Queueable;

    public function __construct(public readonly Subscription $subscription) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your internet service is back on')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line(sprintf(
                'Service on subscription %s has been restored.',
                $this->subscription->subscription_code,
            ))
            ->line('Thank you for settling your account.');

        return $this->signOff($message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'subscription_code' => $this->subscription->subscription_code,
        ];
    }
}
