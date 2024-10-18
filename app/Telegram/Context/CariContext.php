<?php

namespace App\Telegram\Context;

use App\Models\Product;
use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

class CariContext extends AbstractContext
{
    protected static string $regex = '/(cari|find|search)/i';

    /**
     * @param UpdateEvent $event
     * @return bool
     * @throws TelegramSDKException
     */
    public function handle(UpdateEvent $event): mixed
    {
        // get telegram id
        $telegramId = $event->update->message->from->id;
        $telegramService = new TelegramService();
        $teleSettings = new TelegramSettings();

        $checkRegistered = $telegramService->checkRegistered($telegramId);
        if (!$checkRegistered['success']) {
            $telegramService->sendMessage($telegramId, <<<EOD
            Mohon maaf, Anda belum terdaftar di sistem kami. Silakan daftar terlebih dahulu dengan mengetikkan /start atau /register.
            EOD);
            return false;
        }
        $user = $checkRegistered['data'];

        // get search query
        $search = explode(' ', $event->update->message->text, 2)[1];
        $products = Product::query()
            ->active()
            ->whereLike('name', '%' . $search . '%', caseSensitive: false)
            ->limit(3)
            ->get();

        $productsMessage = '';
        $keyboard = '';
        if ($products->isEmpty()) {
            $productsMessage = '⚠Produk tidak ditemukan⚠';
        } else {
            $keyboard = Keyboard::make()
                ->inline();

            foreach ($products as $product) {
                $productsMessage .= <<<EOD
                📦 *{$product->name}*
                💰 Rp. {$product->price}
                📝 {$product->description}
                🛒 Stock => {$product->stock}
                ++++++++++++++++++++++++++++++++
                
                EOD;

                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => $product->name,
                        'callback_data' => 'order_product_' . $product->id
                    ])
                ]);
            }
        }

        $telegramService->sendMessage(
            $telegramId,
            <<<EOD
            👋 Selamat datang di {$teleSettings->store_name}
            =================================
            Berikut hasil dari pencarian mu:
            =================================
            $productsMessage

            Note: 
            produk yang ditampilkan adalah maksimal 3 produk teratas yang ditemukan.
            Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
            EOD,
            button: $keyboard ?? null
        );

        return true;
    }
}
