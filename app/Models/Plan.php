<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'stripe_price_id',
        'currency',
        'trial_days',
        'website_limit',
        'storage_mb',
        'bandwidth_mb',
        'database_mb',
        'max_upload_mb',
        'max_extracted_mb',
        'max_file_count',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->monthly_price <= 0;
    }

    public function formattedMonthlyPrice(): string
    {
        $amount = number_format((float) $this->monthly_price, 2);

        return strtolower($this->currency) === 'myr'
            ? "RM {$amount}"
            : strtoupper($this->currency)." {$amount}";
    }
}
