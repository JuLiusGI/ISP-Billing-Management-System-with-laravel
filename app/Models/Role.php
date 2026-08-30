<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use Auditable, HasFactory;

    public const SUPER_ADMIN = 'super-admin';

    public const ADMINISTRATOR = 'administrator';

    public const BILLING_STAFF = 'billing-staff';

    public const TECHNICIAN = 'technician';

    public const ACCOUNTANT = 'accountant';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    protected function auditModule(): string
    {
        return 'Administration';
    }

    protected function auditLabel(): string
    {
        return $this->display_name;
    }
}
