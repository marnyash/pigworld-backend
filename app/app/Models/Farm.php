<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Farm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'invite_code',
        'mother_pig_count',
        'piglet_groups',
        'pregnant_pig_count',
        'subscription_plan',
    ];

    protected function casts(): array
    {
        return [
            'mother_pig_count' => 'integer',
            'piglet_groups' => 'array',
            'pregnant_pig_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Farm $farm) {
            $farm->invite_code ??= self::generateInviteCode();
        });
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    /** @return BelongsToMany<User, Farm> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('permissions')->withTimestamps();
    }

    /** @return HasMany<FarmJoinRequest, Farm> */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(FarmJoinRequest::class);
    }

    /** @return HasMany<Customer, Farm> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<CustomerOrder, Farm> */
    public function customerOrders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    /** @return HasMany<Animal, Farm> */
    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    public function feedStocks(): HasMany
    {
        return $this->hasMany(FeedStock::class);
    }

    public function feedUsages(): HasMany
    {
        return $this->hasMany(FeedUsage::class);
    }

    /** @return HasMany<Payment, Farm> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<HealthRecord, Farm> */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    /** @return HasMany<GrowthRecord, Farm> */
    public function growthRecords(): HasMany
    {
        return $this->hasMany(GrowthRecord::class);
    }

    /** @return HasMany<InventoryItem, Farm> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
