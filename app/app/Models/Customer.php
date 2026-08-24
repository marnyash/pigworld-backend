<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['farm_id', 'created_by', 'name', 'email', 'phone', 'company', 'address', 'type', 'status', 'notes'];

    /** @return BelongsTo<Farm, Customer> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<User, Customer> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CustomerInteraction, Customer> */
    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest('occurred_at');
    }
}
