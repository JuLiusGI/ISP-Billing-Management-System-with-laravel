<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\BuildsCustomerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use BuildsCustomerMail, Queueable;

    public function __construct(public readonly Payment $payment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Payment received — thank you')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line(sprintf(
                'We have received your payment of %s on %s.',
                $this->money($this->payment->amount),
                $this->payment->payment_date->format('d M Y'),
            ))
            ->line('Reference: '.$this->payment->payment_reference);

        $outstanding = $notifiable->outstandingBalance();

        // Telling someone their balance is clear is worth more than the receipt
        // itself; telling them it is not avoids a second call.
        $message->line(bccomp($outstanding, '0', 2) === 1
            ? 'Remaining balance on your account: '.$this->money($outstanding)
            : 'Your account is fully settled.');

        return $this->signOff($message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_reference' => $this->payment->payment_reference,
            'amount' => (string) $this->payment->amount,
            'payment_date' => $this->payment->payment_date->toDateString(),
        ];
    }
}
