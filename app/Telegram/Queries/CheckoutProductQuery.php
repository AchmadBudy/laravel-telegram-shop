<?php

namespace App\Telegram\Queries;

use App\Models\Product;
use App\Services\PaymentService;
use App\Services\TelegramService;
use App\Settings\PaydisiniSettings;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

class CheckoutProductQuery extends AbstractQuery
{
    protected static string $regex = '/checkout_product/';

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
        $paydisiniSettings = new PaydisiniSettings();
        $arguments = explode('_', $event->update->callbackQuery->data);
        $paymentChannel = $arguments[2];
        $productId = $arguments[3];
        $amount = $arguments[4];


        // call payment service to create transaction
        $response = $paymentService->createPaymentSingleProduct(
            $telegramId,
            $productId,
            $amount,
            $paymentChannel
        );
        if (!$response['success']) {
            Log::error($response);
            $telegramService->sendMessage($telegramId, 'Mohon maaf, terjadi kesalahan saat melakukan transaksi. Silakan coba beberapa saat lagi.');
            return true;
        }
        // delete old message
        $telegramService->deleteMessage($telegramId, $event->update->callbackQuery->message->messageId);

        if ($response['isBalancePayment']) {
            if ($response['isFile']) {
                try {
                    $event->telegram->sendDocument([
                        'chat_id' => $telegramId,
                        'document' => $response['file_path'],
                        'caption' => $response['messageToUser'],
                        'parse_mode' => 'Markdown',
                    ]);
                } catch (\Throwable $th) {
                    Log::error('Failed to send document on payment number : ' . $response['data']->payment_number);
                    Log::error($th->getMessage());
                }
            } else {
                $telegramService->sendMessage($telegramId, $response['messageToUser']);
            }
        } else {
            try {

                $message = $event->telegram->sendPhoto([
                    'chat_id' => $telegramId,
                    'photo' => InputFile::create($response['data']->payment_qr),
                    'caption' => $response['messageToUser'],
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => 'Cek Pembayaran',
                                    'callback_data' => 'check_payment_' . $response['data']->payment_number
                                ],
                                [
                                    'text' => 'Batal',
                                    'callback_data' => 'cancel_payment_' . $response['data']->payment_number
                                ]
                            ],
                        ]
                    ]),
                ]);

                // update message id in transaction
                $response['data']->message_id = $message->messageId;
                $response['data']->save();
            } catch (\Throwable $th) {
                Log::error('Failed to edit message media: ' . $response['data']->payment_qr);
                Log::error($th->getMessage());
            }
        }


        return true;
    }
}
