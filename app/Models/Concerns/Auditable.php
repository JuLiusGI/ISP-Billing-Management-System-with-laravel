<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records create, update, delete and restore on a model.
 *
 * Applied to the models whose changes matter for the trail rather than to
 * every model: a log of cache rows or pivot writes would bury the entries
 * someone actually needs to find.
 *
 * A model using this declares its module, and may narrow what is recorded:
 *
 *   protected function auditModule(): string { return 'Customers'; }
 *   protected array $auditExclude = ['last_login_at'];
 *
 * Update entries carry only the attributes that actually changed. Storing the
 * whole row twice would make a one-field correction indistinguishable from a
 * rewrite of the record.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditEntry('created', null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changed = $model->auditableValues($model->getChanges());

            // Touching a row without changing anything auditable is not an
            // event; only excluded columns moved.
            if ($changed === []) {
                return;
            }

            $before = array_intersect_key(
                $model->auditableValues($model->getOriginal()),
                $changed
            );

            $model->writeAuditEntry('updated', $before, $changed);
        });

        static::deleted(function (Model $model): void {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            $model->writeAuditEntry($soft ? 'archived' : 'deleted', null, null);
        });

        // "restored" is contributed by the SoftDeletes trait, so it only exists
        // on models that use it. Registering it on the others throws.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model): void {
                $model->writeAuditEntry('restored', null, null);
            });
        }
    }

    /** The section of the application this model belongs to. */
    abstract protected function auditModule(): string;

    /**
     * A short human description of the record, used so the log reads without
     * having to open every entry.
     */
    protected function auditLabel(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * Attributes never written to the trail for this model. Secrets are
     * redacted centrally as well; this is for noise.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditableValues(array $values): array
    {
        $ignored = array_merge(
            ['created_at', 'updated_at', 'deleted_at'],
            $this->auditExcluded()
        );

        $values = array_diff_key($values, array_flip($ignored));

        // Enums and dates go in as their stored scalar so the entry reads the
        // same however the model casts them later.
        return array_map(
            fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : $value,
            $values
        );
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function writeAuditEntry(string $action, ?array $old, ?array $new): void
    {
        app(AuditLogger::class)->log(
            action: $action,
            module: $this->auditModule(),
            subject: $this,
            description: $this->auditLabel(),
            old: $old,
            new: $new,
        );
    }
}
