<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'status',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return HasMany<Payment, $this> */
    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    // -----------------------------------------------------------------
    // Attributes
    // -----------------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1).substr($this->last_name, 0, 1));
    }

    // -----------------------------------------------------------------
    // Roles and abilities
    //
    // These answer "what does this user have?". Enforcement runs through
    // the gate registered in AppServiceProvider and the model policies.
    // -----------------------------------------------------------------

    private ?Collection $abilityCache = null;

    public function hasRole(string ...$roles): bool
    {
        return $this->roles->whereIn('name', $roles)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Every ability granted by this user's roles, de-duplicated.
     *
     * Memoised for the lifetime of the instance: a single page renders
     * dozens of ability checks, and each would otherwise re-walk the
     * loaded role and permission relations.
     *
     * @return Collection<int, string>
     */
    public function abilities(): Collection
    {
        return $this->abilityCache ??= $this->roles
            ->loadMissing('permissions')
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->abilities()->contains($permission);
    }

    /**
     * Drops the memoised abilities. Call after changing this user's roles
     * within a single request, otherwise the stale set is reused.
     */
    public function forgetAbilities(): static
    {
        $this->abilityCache = null;
        $this->unsetRelation('roles');

        return $this;
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<User>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /** @param  Builder<User>  $query */
    public function scopeWithRole(Builder $query, string $role): void
    {
        $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    /**
     * Sign-in bookkeeping is covered by the authentication events;
     * logging it here as well would bury real account changes.
     *
     * @var array<int, string>
     */
    protected array $auditExclude = ['last_login_at', 'last_login_ip'];

    protected function auditModule(): string
    {
        return 'Administration';
    }

    protected function auditLabel(): string
    {
        return $this->full_name;
    }
}
