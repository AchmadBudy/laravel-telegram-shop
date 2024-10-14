<?php

namespace App\Telegram\Context;

use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use Telegram\Bot\Events\UpdateEvent;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

class InformationContext extends AbstractContext
{
    protected static string $regex = '/(information|informasi|info)/i';

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
        ─────〔 DATA USER 〕─────
        🆔 ID: {$telegramId}
        🧑 Nama: {$user->first_name} {$user->last_name}
        📊 Saldo: Rp. {$user->balance}
        username: {$user->username}

        📝 Anda sudah terdaftar di sistem kami.
        Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
        EOD,
            button: Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '🛒 Stock']),
                    Keyboard::button(['text' => '📝 Cara Order']),
                ])
                ->row([
                    Keyboard::button(['text' => 'Informasi']),
                    Keyboard::button(['text' => 'Deposit']),
                ])
        );
    }
}
