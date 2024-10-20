<?php

namespace App\Telegram\Context;

use App\Models\Product;
use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class StockContext extends AbstractContext
{
    protected static string $regex = '/(stock|stok)/i';

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

        $products = Product::active()
            ->orderBy('name')
            ->get();

        $message = "📦 Daftar Produk Di $teleSettings->store_name\n\n";
        $message .= "Untuk melakukan pembelian, silakan ketik 'cari [nama produk]' atau simpel nya 'cari viu'.\n\n";

        foreach ($products as $product) {
            $message .= "====================\n";
            $message .= "📦 {$product->name}\n";
            $message .= "💰 Rp. " . number_format($product->price, 0, ',', '.') . "\n";
            $message .= "📊 Stok: {$product->stock}\n";
            $message .= "====================\n\n";
        }

        if ($products->isEmpty()) {
            $message .= '⚠Produk lagi kosong⚠';
        }

        $message .= "\n\nUntuk melakukan pembelian, silakan ketik 'cari [nama produk]' atau simpel nya 'cari viu'.";

        $telegramService->sendMessage(
            $telegramId,
            $telegramService->escapeMarkdown($message),
        );
    }
}
