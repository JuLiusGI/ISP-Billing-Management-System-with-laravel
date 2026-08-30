<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\BuildsCustomerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceGenerated extends Notification implements ShouldQueue
{
    use BuildsCustomerMail, Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(sprintf('Invoice %s from %s', $this->invoice->invoice_number, $this->company()['name']))
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line(sprintf(
                'Invoice %s has been issued for %s.',
                $this->invoice->invoice_number,
                $this->money($this->invoice->total_amount),
            ))
            ->line('Due date: '.$this->invoice->due_date->format('d M Y'));

        return $this->signOff($message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => (string) $this->invoice->total_amount,
            'due_date' => $this->invoice->due_date->toDateString(),
        ];
    }
}
