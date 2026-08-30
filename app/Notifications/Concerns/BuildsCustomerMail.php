<?php

namespace App\Notifications\Concerns;

use App\Services\SettingsService;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared framing for customer mail.
 *
 * Every message signs off as the ISP configured in system settings rather than
 * a name compiled into the class, so an installation that renames itself does
 * not have to be redeployed.
 */
trait BuildsCustomerMail
{
    protected function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    protected function company(): array
    {
        return $this->settings()->company();
    }

    protected function money(string|float|int|null $amount): string
    {
        return $this->settings()->currencySymbol().number_format((float) $amount, 2);
    }

    /** Applies the signature and contact footer to a built message. */
    protected function signOff(MailMessage $message): MailMessage
    {
        $company = $this->company();

        $message->salutation('— '.$company['name']);

        $contact = array_filter([$company['phone'], $company['email'], $company['website']]);

        if ($contact !== []) {
            $message->line('Questions? Reach us at '.implode(' · ', $contact));
        }

        return $message;
    }
}
