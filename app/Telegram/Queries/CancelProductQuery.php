<?php

namespace App\Telegram\Queries;

use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class CancelProductQuery extends AbstractQuery
{
    protected static string $regex = '/cancel_order/';

    /**
     * @param UpdateEvent $event
     * @return bool
     * @throws TelegramSDKException
     */
    public function handle(UpdateEvent $event): mixed
    {
        $telegramId = $event->update->callbackQuery->from->id;
        $telegramService = new TelegramService();

        // delete message
        $telegramService->deleteMessage($telegramId, $event->update->callbackQuery->message->messageId);

        // send message
        $telegramService->sendMessage($telegramId, 'Order dibatalkan');
        return true;
    }
}
