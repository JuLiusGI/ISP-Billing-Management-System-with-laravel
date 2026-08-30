<?php

namespace App\Services;

use App\Contracts\ServiceProvisioner;
use App\Enums\CustomerConnectionStatus;
use App\Enums\SubscriptionStatus;
use App\Models\InternetPlan;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ServiceReactivated;
use App\Notifications\ServiceSuspended;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Subscription writes and the service status transitions.
 *
 * Status changes are the reason this is a service rather than controller code:
 * each one has to move the subscription, write its log entry and reconcile the
 * customer's connection status inside a single transaction.
 */
class SubscriptionService
{
    private const CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly ServiceProvisioner $provisioner,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Creates a subscription, copying the plan's pricing across.
     *
     * The rate is copied rather than referenced so a later plan repricing
     * cannot rewrite what this customer agreed to pay.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Subscription
    {
        $plan = InternetPlan::findOrFail($attributes['internet_plan_id']);

        $attributes['monthly_rate'] ??= $plan->monthly_price;
        $attributes['installation_fee'] ??= $plan->installation_fee;
        $attributes['status'] ??= SubscriptionStatus::Pending->value;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $actor): Subscription {
                    $subscription = Subscription::create($attributes);

                    $this->log($subscription, null, $subscription->status, 'Subscription created', $actor);
                    $this->syncCustomerConnection($subscription);

                    return $subscription;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::CODE_ATTEMPTS || ! str_contains($e->getMessage(), 'subscription_code')) {
                    throw $e;
                }
                // Retry; the model generates a fresh code on the next attempt.
            }
        }
    }

    /**
     * Updates the editable fields. Status is deliberately not one of them:
     * it moves through changeStatus so every change leaves a log entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Subscription $subscription, array $attributes): Subscription
    {
        unset($attributes['status'], $attributes['subscription_code'], $attributes['customer_id']);

        $subscription->update($attributes);

        return $subscription->refresh();
    }

    /**
     * Moves a subscription to a new status, recording why and by whom.
     *
     * @throws DomainException when the transition is not allowed
     */
    public function changeStatus(
        Subscription $subscription,
        SubscriptionStatus $target,
        ?string $reason,
        ?User $actor = null,
        bool $automatic = false,
    ): Subscription {
        $from = $subscription->status;

        if ($from === $target) {
            throw new DomainException("This subscription is already {$target->label()}.");
        }

        if (! $from->canTransitionTo($target)) {
            throw new DomainException(
                "A {$from->label()} subscription cannot be moved to {$target->label()}."
            );
        }

        $subscription = DB::transaction(function () use ($subscription, $from, $target, $reason, $actor, $automatic): Subscription {
            $changes = ['status' => $target];

            // Activating for the first time stamps the activation date.
            if ($target === SubscriptionStatus::Active && $subscription->activation_date === null) {
                $changes['activation_date'] = now()->toDateString();
            }

            $subscription->update($changes);

            $this->log($subscription, $from, $target, $reason, $actor, $automatic);
            $this->syncCustomerConnection($subscription);

            // service_status_logs is the domain record of what happened to the
            // line; this is the same event in the cross-module trail, where
            // someone auditing an account looks first.
            app(AuditLogger::class)->log(
                action: 'service_status_changed',
                module: 'Subscriptions',
                subject: $subscription,
                description: sprintf(
                    '%s: %s to %s%s',
                    $subscription->subscription_code,
                    $from->label(),
                    $target->label(),
                    $reason ? ' — '.$reason : ''
                ),
                old: ['status' => $from->value],
                new: ['status' => $target->value],
                actor: $actor,
            );

            return $subscription->refresh();
        });

        // Deliberately outside the transaction. Provisioning talks to the
        // network, and holding row locks open across a device call is how a
        // slow router turns into a database problem. The status change is
        // already durable by this point.
        $this->provision($subscription, $target);
        $this->notifyCustomer($subscription, $from, $target, $reason);

        return $subscription;
    }

    /**
     * Tells the customer their line went down or came back.
     *
     * Only these two transitions are worth a message. A move between pending,
     * expired and cancelled is administrative, and mailing about it trains
     * people to ignore the ones that matter.
     */
    private function notifyCustomer(
        Subscription $subscription,
        SubscriptionStatus $from,
        SubscriptionStatus $target,
        ?string $reason,
    ): void {
        $customer = $subscription->customer;

        if (! $customer) {
            return;
        }

        if ($target === SubscriptionStatus::Suspended) {
            $this->notifier->send($customer, 'service_suspended', new ServiceSuspended($subscription, $reason));

            return;
        }

        // Reactivation only counts as such coming back from suspension.
        if ($target === SubscriptionStatus::Active && $from === SubscriptionStatus::Suspended) {
            $this->notifier->send($customer, 'service_reactivated', new ServiceReactivated($subscription));
        }
    }

    /**
     * Pushes the new state to the network.
     *
     * Nothing reaches a device until a real driver is bound; the null driver
     * records what it would have done. A provisioning failure must not undo a
     * recorded status change, so it is logged rather than thrown.
     */
    private function provision(Subscription $subscription, SubscriptionStatus $target): void
    {
        try {
            match ($target) {
                SubscriptionStatus::Active => $this->provisioner->activate($subscription),
                SubscriptionStatus::Suspended => $this->provisioner->suspend($subscription),
                SubscriptionStatus::Cancelled, SubscriptionStatus::Expired => $this->provisioner->terminate($subscription),
                // Pending has nothing to push: the line was never brought up.
                SubscriptionStatus::Pending => null,
            };
        } catch (Throwable $e) {
            Log::error('Service provisioning failed after a status change.', [
                'subscription' => $subscription->subscription_code,
                'target_status' => $target->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keeps the customer's connection status in step with their subscriptions.
     *
     * A customer is connected while any subscription is active; otherwise the
     * line is down, unless nothing has been switched on yet.
     */
    private function syncCustomerConnection(Subscription $subscription): void
    {
        $customer = $subscription->customer;

        if (! $customer) {
            return;
        }

        $hasActive = $customer->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->exists();

        $everActivated = $customer->subscriptions()
            ->whereNotNull('activation_date')
            ->exists();

        $customer->update([
            'connection_status' => match (true) {
                $hasActive => CustomerConnectionStatus::Connected,
                $everActivated => CustomerConnectionStatus::Disconnected,
                default => CustomerConnectionStatus::Pending,
            },
        ]);
    }

    private function log(
        Subscription $subscription,
        ?SubscriptionStatus $from,
        SubscriptionStatus $to,
        ?string $reason,
        ?User $actor,
        bool $automatic = false,
    ): void {
        ServiceStatusLog::create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'changed_by' => $actor?->id,
            'is_automatic' => $automatic,
        ]);
    }
}
