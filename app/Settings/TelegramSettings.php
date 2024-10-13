<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TelegramSettings extends Settings
{
    public string $token;
    public string $random_string;
    public string $bot_username;
    public string $owner_username;
    public string $bot_url;
    public string $store_name;


    public static function group(): string
    {
        return 'telegram';
    }

    public static function encrypted(): array
    {
        return [
            'token'
        ];
    }
}
