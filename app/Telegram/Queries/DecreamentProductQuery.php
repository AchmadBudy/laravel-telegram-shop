<?php

namespace App\Telegram\Queries;

use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class DecreamentProductQuery extends AbstractQuery
{
    protected static string $regex = '/decreament_product/';

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
        $amount = $arguments[3];

        // call show order product
        $response = $telegramService->showOrderProduct($productId, amount: $amount, decreament: true);

        if (!$response['success']) {
            $event->telegram->answerCallbackQuery([
                'callback_query_id' => $event->update->callbackQuery->id,
                'text' => $response['message'],
            ]);
            return true;
        }
        try {
            $event->telegram->editMessageText([
                'chat_id' => $telegramId,
                'message_id' => $event->update->callbackQuery->message->messageId,
                'text' => $response['data']['message'],
                'reply_markup' => $response['data']['keyboard'],
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $th) {
            // send message
            $telegramService->sendMessage($telegramId, $response['data']['message'], button: $response['data']['keyboard']);
        }

        return true;
    }
}
