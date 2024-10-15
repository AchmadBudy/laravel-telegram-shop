<?php

namespace App\Telegram\Queries;

use App\Enums\OrderStatus;
use App\Models\DepositHistory;
use App\Services\PaydisiniService;
use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Str;
use Telegram\Bot\FileUpload\InputFile;

class CheckPaymentQuery extends AbstractQuery
{
    protected static string $regex = '/check_payment/';

    /**
     * @param UpdateEvent $event
     * @return bool
     * @throws TelegramSDKException
     */
    public function handle(UpdateEvent $event): mixed
    {
        $telegramId = $event->update->callbackQuery->from->id;
        $telegramService = new TelegramService();
        $paydisiniService = new PaydisiniService();
        $teleSettings = new TelegramSettings();
        $arguments = explode('_', $event->update->callbackQuery->data);
        $paymentCode = $arguments[2];

        // send api call to paydisini
        $response = $paydisiniService->checkTransaction($paymentCode);
        if (!$response['success']) {
            $telegramService->sendMessage($telegramId, 'Mohon maaf, terjadi kesalahan saat melakukan deposit. Silakan coba beberapa saat lagi.');
            return false;
        }

        $data = $response['data'];

        if ($data['status'] == 'Pending') {
            $telegramService->sendMessage($telegramId, 'Lakukan pembayaran sebelum waktu habis.');
            return true;
        } elseif ($data['status'] == 'Canceled') {
            $telegramService->sendMessage($telegramId, 'Pembayaran telah dibatalkan. Jika pesanan/deposit belum berubah silahkan hubungi @' . $teleSettings->owner_username);
            return true;
        } elseif ($data['status'] == 'Success') {
            $telegramService->sendMessage($telegramId, 'Pembayaran telah terverifikasi, jika pesanan/deposit belum berubah silahkan hubungi @' . $teleSettings->owner_username);
            return true;
        }

        return true;
    }
}
