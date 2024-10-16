<?php

namespace App\Telegram\Queries;

use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class OrderProductQuery extends AbstractQuery
{
    protected static string $regex = '/order_product/';

    /**
     * @param UpdateEvent $event
     * @return bool
     * @throws TelegramSDKException
     */
    public function handle(UpdateEvent $event): mixed
    {
        $telegramId = $event->update->callbackQuery->from->id;
        $paymentService = new PaymentService();
        $telegramService = new TelegramService();
        $arguments = explode('_', $event->update->callbackQuery->data);
        $productId = $arguments[2];

        // call show order product
        $response = $telegramService->showOrderProduct($productId);
        // delete message
        $telegramService->deleteMessage($telegramId, $event->update->callbackQuery->message->messageId);

        if (!$response['success']) {
            $event->telegram->answerCallbackQuery([
                'callback_query_id' => $event->update->callbackQuery->id,
                'text' => $response['message'],
            ]);
            return true;
        }

        // send message
        $telegramService->sendMessage($telegramId, $response['data']['message'], button: $response['data']['keyboard']);

        return true;
    }
}
