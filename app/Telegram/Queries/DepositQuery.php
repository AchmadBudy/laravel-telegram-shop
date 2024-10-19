<?php

namespace App\Telegram\Queries;

use App\Enums\OrderStatus;
use App\Models\DepositHistory;
use App\Models\TelegramUser;
use App\Services\PaydisiniService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Str;
use Telegram\Bot\FileUpload\InputFile;

class DepositQuery extends AbstractQuery
{
    protected static string $regex = '/deposit/';

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
        $arguments = explode('_', $event->update->callbackQuery->data);
        $serviceCode = $arguments[1];
        $amount = $arguments[2];


        DB::beginTransaction();
        try {
            // get telegram user
            $telegramUser = TelegramUser::where('telegram_id', $telegramId)->first();
            // create record in deposithistory
            $depositHistory = DepositHistory::create([
                'telegram_user_id' => $telegramUser->id,
                'total_deposit' => $amount,
                'status' => OrderStatus::PENDING,
                'service_code' => $serviceCode,
            ]);

            // create transaction code
            $transactionCode = 'DEPOSIT-TELE' .  Str::padLeft($depositHistory->id, 11, '0');

            // send api call to paydisini
            $response = $paydisiniService->createTransaction($transactionCode, $amount, $serviceCode, 'Deposit balance Rp. ' . number_format($amount, 0, ',', '.'));
            if (!$response['success']) {
                Log::error($response);
                $telegramService->sendMessage($telegramId, 'Mohon maaf, terjadi kesalahan saat melakukan deposit. Silakan coba beberapa saat lagi.');
                throw new \Exception('Failed to create transaction');
            }
            // delete message
            $telegramService->deleteMessage($telegramId, $event->update->callbackQuery->message->messageId);

            // send message to user
            $message = $event->telegram->sendPhoto([
                'chat_id' => $telegramId,
                'photo' => InputFile::create($response['data']['qrcode_url']),
                'caption' => <<<EOD
             Total deposit: Rp. {$amount}
             Payment type: {$response['data']['service_name']}
             Payment number: {$transactionCode}
 
             Silahkan lakukan pembayaran sebelum batas waktu yang ditentukan.
             exp date: {$response['data']['expired']}
             EOD,
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Cek Pembayaran',
                                'callback_data' => 'check_payment_' . $transactionCode
                            ],
                            [
                                'text' => 'Batal',
                                'callback_data' => 'cancel_payment_' . $transactionCode
                            ]
                        ],
                    ]
                ])
            ]);

            // update message id
            $depositHistory->update([
                'message_id' => $message->messageId,
                'transaction_code' => $transactionCode,
                'payment_type' => $response['data']['service_name'],
                'payment_status' => Str::lower($response['data']['status']),
                'payment_link' => $response['data']['checkout_url'],
                'payment_qr' => $response['data']['qrcode_url'],
                'payment_number' => $transactionCode,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return true;
        }


        return true;
    }
}
