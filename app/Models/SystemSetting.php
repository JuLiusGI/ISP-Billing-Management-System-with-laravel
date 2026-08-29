<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    /** The stored text value converted to its declared type. */
    public function getTypedValueAttribute(): mixed
    {
        return $this->type->cast($this->value);
    }

    /** @param  Builder<SystemSetting>  $query */
    public function scopeGroup(Builder $query, string $group): void
    {
        $query->where('group', $group);
    }
}
