<?php

namespace App\Telegram\Context;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Events\UpdateEvent;

abstract class AbstractContext
{
    protected static string $regex;

    public static function match(string $data)
    {
        $actions = collect((new TelegramService())->context);
        return $actions
            ->firstWhere(fn($action) => preg_match(forward_static_call([$action, 'getRegex']), $data));
    }

    public static function getRegex(): string
    {
        return static::$regex;
    }

    abstract public function handle(UpdateEvent $event): mixed;
}
