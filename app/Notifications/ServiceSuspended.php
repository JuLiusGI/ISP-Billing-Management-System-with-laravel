<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\BuildsCustomerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceSuspended extends Notification implements ShouldQueue
{
    use BuildsCustomerMail, Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your internet service has been suspended')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line(sprintf(
                'Service on subscription %s has been suspended.',
                $this->subscription->subscription_code,
            ));

        if ($this->reason) {
            $message->line('Reason: '.$this->reason);
        }

        $outstanding = $notifiable->outstandingBalance();

        if (bccomp($outstanding, '0', 2) === 1) {
            $message->line('Outstanding balance: '.$this->money($outstanding));
            $message->line('Service is restored once the balance is settled.');
        }

        return $this->signOff($message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'subscription_code' => $this->subscription->subscription_code,
            'reason' => $this->reason,
        ];
    }
}
