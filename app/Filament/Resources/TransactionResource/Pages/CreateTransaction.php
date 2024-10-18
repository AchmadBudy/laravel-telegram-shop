<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\TelegramUser;
use App\Services\PaymentService;
use App\Services\TelegramService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Telegram\Bot\FileUpload\InputFile;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // dd($data);
        $paymentService = new PaymentService();
        $telegramService = new TelegramService();

        try {
            $telegramUser = TelegramUser::find($data['telegram_user_id']);

            if ($data['total_price'] < 100) {
                $data['payment_method']  = 'balance';
            }

            // call payment service to create transaction
            $response = $paymentService->createPaymentSingleProduct(
                $telegramUser->telegram_id,
                $data['product_id'],
                $data['quantity'],
                $data['payment_method'],
                $data['discount']
            );

            if (!$response['success']) {
                throw new \Exception($response['message']);
            }

            if ($response['isBalancePayment']) {
                if ($response['isFile']) {
                    $telegramService->telegram->sendDocument([
                        'chat_id' =>  $telegramUser->telegram_id,
                        'document' => $response['file_path'],
                        'caption' => $response['messageToUser'],
                        'parse_mode' => 'Markdown',
                    ]);
                } else {
                    $telegramService->sendMessage($telegramUser->telegram_id, $response['messageToUser']);
                }
            } else {
                $message = $telegramService->telegram->sendPhoto([
                    'chat_id' => $telegramUser->telegram_id,
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
            }

            return $response['data'];
        } catch (\Throwable $th) {
            //throw $th;
            Notification::make()
                ->warning()
                ->title('Failed to create transaction')
                ->body($th->getMessage())
                ->send();


            $this->halt();
        }
    }
}
