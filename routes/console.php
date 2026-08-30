<?php

use App\Console\Commands\GenerateInvoices;
use App\Console\Commands\ProcessServiceStatus;
use App\Console\Commands\UpdateOverdueInvoices;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled billing
|--------------------------------------------------------------------------
|
| Laravel 12 registers the schedule here rather than in a kernel class.
|
| The order within the day is deliberate: invoices are issued first, the
| overdue sweep then reflects yesterday's due dates, and service status is
| applied last so a line is never suspended over an invoice that was about to
| be marked overdue in the same run.
|
| Every job carries withoutOverlapping() so a slow run cannot be started again
| on top of itself, and runInBackground() so one long job does not delay the
| next. All three are safe to run repeatedly regardless — that is a property of
| the commands, not of the locking.
|
| The schedule needs one cron entry on the server:
|
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
| On Windows, use Task Scheduler to run `php artisan schedule:run` every minute.
*/

Schedule::command(GenerateInvoices::class)
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => logger()->error('Scheduled invoice generation failed.'));

Schedule::command(UpdateOverdueInvoices::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(ProcessServiceStatus::class)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => logger()->error('Scheduled service status run failed.'));
