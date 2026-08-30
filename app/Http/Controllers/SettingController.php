<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The system settings screens.
 *
 * Settings are edited one group at a time rather than as a single form. A page
 * that saves company details, billing rules, suspension policy and notification
 * switches together makes a careless save able to change all four.
 */
class SettingController extends Controller
{
    /** The tabs, and the keys each one owns. */
    private const GROUPS = [
        'company' => 'Company',
        'billing' => 'Billing',
        'service' => 'Service',
        'notifications' => 'Notifications',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        $this->authorize('settings.view');

        $group = $request->query('group');
        $group = is_string($group) && array_key_exists($group, self::GROUPS) ? $group : 'company';

        return view('settings.index', [
            'groups' => self::GROUPS,
            'group' => $group,
            'settings' => $this->settings,
            'company' => $this->settings->company(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        match ($group) {
            'company' => $this->saveCompany($request),
            'billing' => $this->saveBilling($request),
            'service' => $this->saveService($request),
            'notifications' => $this->saveNotifications($request),
        };

        return redirect()
            ->route('settings.index', ['group' => $group])
            ->with('success', self::GROUPS[$group].' settings have been saved.');
    }

    private function saveCompany(UpdateSettingsRequest $request): void
    {
        $this->settings->set('company.name', $request->string('company_name')->toString());
        $this->settings->set('company.address', $request->input('company_address'));
        $this->settings->set('company.phone', $request->input('company_phone'));
        $this->settings->set('company.email', $request->input('company_email'));
        $this->settings->set('company.website', $request->input('company_website'));

        $existing = $this->settings->companyLogoPath();

        if ($request->hasFile('logo')) {
            $this->settings->set('company.logo_path', $request->file('logo')->store('branding', 'public'));
            $this->deleteLogo($existing);

            return;
        }

        if ($request->boolean('remove_logo')) {
            $this->settings->set('company.logo_path', null);
            $this->deleteLogo($existing);
        }
    }

    private function saveBilling(UpdateSettingsRequest $request): void
    {
        $this->settings->set('billing.default_cycle', $request->string('default_cycle')->toString());
        $this->settings->set('billing.grace_period_days', $request->integer('grace_period_days'));
        // Prefixes are upper-cased so INV and inv cannot both end up in use.
        $this->settings->set('billing.invoice_prefix', $request->string('invoice_prefix')->upper()->toString());
        $this->settings->set('billing.receipt_prefix', $request->string('receipt_prefix')->upper()->toString());
        $this->settings->set('billing.currency', $request->string('currency')->upper()->toString());
        $this->settings->set('billing.currency_symbol', $request->string('currency_symbol')->toString());
        $this->settings->set('billing.tax_enabled', $request->boolean('tax_enabled'));
        $this->settings->set('billing.tax_rate', number_format((float) $request->input('tax_rate'), 2, '.', ''));
    }

    private function saveService(UpdateSettingsRequest $request): void
    {
        $this->settings->set('service.auto_suspend_enabled', $request->boolean('auto_suspend_enabled'));
        $this->settings->set('service.suspend_after_days_overdue', $request->integer('suspend_after_days_overdue'));
        $this->settings->set('service.default_status', $request->string('default_status')->toString());
    }

    private function saveNotifications(UpdateSettingsRequest $request): void
    {
        foreach ([
            'email_enabled', 'on_invoice_created', 'on_payment_received',
            'on_invoice_overdue', 'on_service_suspended', 'on_service_reactivated',
        ] as $key) {
            $this->settings->set("notifications.{$key}", $request->boolean($key));
        }
    }

    /** Removes a superseded logo file, leaving the disk tidy. */
    private function deleteLogo(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
