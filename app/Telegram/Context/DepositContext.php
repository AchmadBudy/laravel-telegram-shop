<?php

namespace App\Telegram\Context;

use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

class DepositContext extends AbstractContext
{
    protected static string $regex = '/(cara deposit)/i';

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

        $telegramService->sendMessage(
            $telegramId,
            <<<EOD
        👋 Selamat datang di {$teleSettings->store_name}
            
        📌 Deposit
        Silahkan lakukan "/deposit <jumlah>" untuk melakukan deposit.

        💡 Contoh:
        /deposit 100000

        📝 Note:
        - Minimal deposit adalah Rp. 1000

        Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
        EOD
        );
    }
}
