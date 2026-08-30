<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single gate every customer notification passes through.
 *
 * Whether a message goes out depends on three things — the master switch, the
 * per-event switch, and whether the customer has an address at all — and that
 * decision belongs in one place rather than repeated at each dispatch site,
 * where one of the three eventually gets forgotten.
 *
 * Sending is best-effort. A mail server being down must not roll back the
 * payment that triggered the receipt, so failures are logged and swallowed.
 */
class CustomerNotifier
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  string  $event  the settings key suffix, e.g. "payment_received"
     */
    public function send(Customer $customer, string $event, Notification $notification): bool
    {
        if (! $this->shouldSend($customer, $event)) {
            return false;
        }

        try {
            $customer->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send a customer notification.', [
                'customer' => $customer->account_number,
                'event' => $event,
                'notification' => $notification::class,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Exposed so a caller can skip building a notification it would not send.
     */
    public function shouldSend(Customer $customer, string $event): bool
    {
        return $this->settings->emailNotificationsEnabled()
            && $this->settings->notifiesOn($event)
            && filled($customer->email);
    }
}
