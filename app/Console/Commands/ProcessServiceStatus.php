<?php

namespace App\Console\Commands;

use App\Services\ServiceStatusAutomation;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Applies the service policy: expire lapsed lines, and suspend lines whose
 * customer is far enough behind.
 *
 * Automatic suspension is off unless an administrator turns it on, and the
 * command says so rather than doing nothing silently. Cutting customers off
 * is not something an installation should start doing because a scheduler was
 * switched on.
 */
class ProcessServiceStatus extends Command
{
    protected $signature = 'billing:process-service-status
                            {--dry-run : Report what would change without changing anything}';

    protected $description = 'Expire lapsed services and suspend those overdue beyond the configured threshold';

    public function handle(ServiceStatusAutomation $automation, SettingsService $settings): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run: nothing will be changed.');
        }

        // Expiry first. A line that has run out should not also be reported as
        // suspended for non-payment in the same run.
        $expired = $automation->expireLapsed($dryRun);
        $this->report('Expired', $expired, 'expired');

        if (! $settings->autoSuspendEnabled()) {
            $this->comment('Automatic suspension is disabled in system settings; no lines were suspended.');

            return $this->finish($expired, null);
        }

        $this->line(sprintf(
            'Suspension threshold: <info>%d day(s)</info> past due.',
            $settings->suspendAfterDaysOverdue(),
        ));

        $suspended = $automation->suspendOverdue($dryRun);
        $this->report('Suspended', $suspended, 'suspended');

        return $this->finish($expired, $suspended);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function report(string $label, array $summary, string $countKey): void
    {
        $this->table(
            [$label.' (eligible)', 'Changed', 'Skipped', 'Failed'],
            [[$summary['eligible'], $summary[$countKey], $summary['skipped'], $summary['failed']]],
        );

        foreach ($summary['subscriptions'] as $code) {
            $this->line("  {$code}");
        }

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }
    }

    /**
     * @param  array<string, mixed>  $expired
     * @param  array<string, mixed>|null  $suspended
     */
    private function finish(array $expired, ?array $suspended): int
    {
        $failed = $expired['failed'] + ($suspended['failed'] ?? 0);

        Log::info('Scheduled service status run finished.', [
            'expired' => $expired['expired'],
            'suspended' => $suspended['suspended'] ?? 0,
            'failed' => $failed,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
