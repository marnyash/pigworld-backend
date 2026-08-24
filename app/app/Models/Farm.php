<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location'];

    /** @return BelongsToMany<User, Farm> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return HasMany<Customer, Farm> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
