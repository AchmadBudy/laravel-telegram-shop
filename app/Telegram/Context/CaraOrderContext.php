<?php

namespace App\Telegram\Context;

use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;

class CaraOrderContext extends AbstractContext
{
    protected static string $regex = '/(cara order)/i';

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

        $telegramService->sendMessage($telegramId, <<<EOD
        👋 Selamat datang di {$teleSettings->store_name}
        ─────〔 Cara Order 〕─────
        1. Pilih produk yang ingin dipesan dengan mengetikkan "cari <nama produk>".
        2. Pilih produk yang diinginkan.
        3. Sesuaikan jumlah produk yang ingin dipesan.
        4. Klik tombol "Pesan Sekarang".
        5. Pilih metode pembayaran yang diinginkan.
        6. Bayar sesuai nominal yang tertera.
        7. Produk akan segera dikirim setelah pembayaran dikonfirmasi.

        Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
        EOD);
    }
}
