<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PaydisiniSettings extends Settings
{
    public string $api_key;

    public array $payment_channel;

    public string $fee_type;

    public static function group(): string
    {
        return 'paydisini';
    }

    public static function encrypted(): array
    {
        return [
            'api_key'
        ];
    }
}
