<?php

namespace App\Telegram\Queries;

use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class CancelPaymentQuery extends AbstractQuery
{
    protected static string $regex = '/cancel_payment/';

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
        $paymentCode = $arguments[2];

        // call cancel transaction
        $response = $paymentService->cancelPayment($paymentCode, cancelByUser: true);
        if (!$response['success']) {
            Log::error($response);
            $telegramService->sendMessage($telegramId, 'Mohon maaf, terjadi kesalahan saat membatalkan pembayaran. Silakan coba beberapa saat lagi.');
            return true;
        }

        // delete message
        $telegramService->deleteMessage($telegramId, $event->update->callbackQuery->message->messageId);
        // send message
        $telegramService->sendMessage($telegramId, 'Pembayaran telah dibatalkan.');
        return true;
    }
}
