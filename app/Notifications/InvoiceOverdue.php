<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\BuildsCustomerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification implements ShouldQueue
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
        $days = $this->invoice->daysOverdue();

        $message = (new MailMessage)
            ->subject('Overdue invoice '.$this->invoice->invoice_number)
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line(sprintf(
                'Invoice %s was due on %s and remains unpaid.',
                $this->invoice->invoice_number,
                $this->invoice->due_date->format('d M Y'),
            ))
            ->line('Outstanding balance: '.$this->money($this->invoice->balance_due));

        if ($days > 0) {
            $message->line(sprintf('It is now %d day%s past due.', $days, $days === 1 ? '' : 's'));
        }

        // Only warn about suspension where the installation actually does it.
        if ($this->settings()->autoSuspendEnabled()) {
            $message->line(sprintf(
                'Service is suspended automatically once an invoice is %d days overdue.',
                $this->settings()->suspendAfterDaysOverdue(),
            ));
        }

        return $this->signOff($message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'balance_due' => (string) $this->invoice->balance_due,
            'days_overdue' => $this->invoice->daysOverdue(),
        ];
    }
}
