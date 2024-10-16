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
use Telegram\Bot\Keyboard\Keyboard;

class ContinueProductQuery extends AbstractQuery
{
    protected static string $regex = '/continue_order/';

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
        $productId = $arguments[2];
        $amount = $arguments[3];

        // Get product
        $product = Product::query()
            ->active()
            ->where('id', $productId)
            ->first();
        if (!$product) {
            $event->telegram->answerCallbackQuery([
                'callback_query_id' => $event->update->callbackQuery->id,
                'text' => 'Mohon maaf, produk untuk saat ini tidak aktif atau tidak dijual.',
            ]);
            return true;
        }

        // check stock
        if ($product->stock < $amount) {
            $event->telegram->answerCallbackQuery([
                'callback_query_id' => $event->update->callbackQuery->id,
                'text' => 'Mohon maaf, stok produk tidak mencukupi.',
            ]);
            return true;
        }

        $totalPrice = $product->price * $amount;

        // show checkout message
        $message = <<<EOD
        Informasi Pembelian: 
        Produk : 
        ➜ Nama: *{$product->name}*
        ➜ Harga: Rp. *{$product->price}*
        ➜ Jumlah: *{$amount}*
        ➜ Total: Rp. *{$totalPrice}*

        Silahkan pilih tombol pembayaran dibawah ini:
        EOD;

        $button = Keyboard::make()
            ->inline();
        $buttonChannels = [];
        $paymentChannel = $paydisiniSettings->payment_channel;
        foreach ($paymentChannel as $channel) {
            $buttonChannels[] = Keyboard::inlineButton([
                'text' => $channel['name'],
                'callback_data' => 'checkout_product_' . $channel['id'] . '_' . $productId . '_' . $amount
            ]);
        }
        $buttonChannels[] = Keyboard::inlineButton([
            'text' => 'Balance',
            'callback_data' => 'checkout_product_balance_' . $productId . '_' . $amount
        ]);
        $buttonChannels = array_chunk($buttonChannels, 2);
        foreach ($buttonChannels as $row) {
            $button->row($row);
        }
        $button->row([
            Keyboard::inlineButton([
                'text' => 'Batalkan',
                'callback_data' => 'cancel_order'
            ])
        ]);

        try {
            $event->telegram->editMessageText([
                'chat_id' => $telegramId,
                'message_id' => $event->update->callbackQuery->message->messageId,
                'text' => $message,
                'reply_markup' => $button,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $th) {
            $telegramService->sendMessage($telegramId, $message, button: $button);
        }


        return true;
    }
}
