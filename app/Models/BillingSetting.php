<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'provider',
        'enabled',
        'environment',
        'secret_key',
        'category_code',
        'payment_channel',
        'charge_to_customer',
    ];

    protected $hidden = ['secret_key'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'secret_key' => 'encrypted',
            'payment_channel' => 'integer',
            'charge_to_customer' => 'boolean',
        ];
    }

    public static function toyyibPay(): ?self
    {
        return self::query()->where('provider', 'toyyibpay')->first();
    }

    public function baseUrl(): string
    {
        return $this->environment === 'production'
            ? 'https://toyyibpay.com'
            : 'https://dev.toyyibpay.com';
    }
}
