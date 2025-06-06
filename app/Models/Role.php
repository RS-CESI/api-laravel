<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static firstOrCreate(string[] $array, string[] $array)
 * @method static where(string $string, string $role)
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
