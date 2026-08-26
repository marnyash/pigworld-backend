<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class FeedStock extends Model
{
    use HasFactory;

    protected $fillable = ['farm_id', 'created_by', 'name', 'quantity', 'unit', 'location'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
    public function usages(): HasMany { return $this->hasMany(FeedUsage::class); }
}
