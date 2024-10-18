<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Services\TelegramService;
use App\Settings\PaydisiniSettings;
use App\Settings\TelegramSettings;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * This command can be triggered in two ways:
 * /deposit and /depo due to the alias.
 */
class DepositCommand extends Command
{
    protected string $name = 'deposit';
    protected array $aliases = ['depo'];
    protected string $description = 'command to deposit';
    protected string $pattern = '{amount}';

    public function handle()
    {
        $telegramId = $this->getUpdate()->getMessage()->from->id;
        $telegramService = new TelegramService();
        $teleSettings = new TelegramSettings();
        $paydisiniSettings = new PaydisiniSettings();

        $amount = $this->argument('amount');
        if (!is_numeric($amount) || $amount < 500) {
            $telegramService->sendMessage($telegramId, 'Mohon masukkan nominal deposit yang valid.');
            return;
        }

        $checkRegistered = $telegramService->checkRegistered($telegramId);
        if (!$checkRegistered['success']) {
            $telegramService->sendMessage($telegramId, <<<EOD
            Mohon maaf, Anda belum terdaftar di sistem kami. Silakan daftar terlebih dahulu dengan mengetikkan /start atau /register.
            EOD);
            return false;
        }
        $user = $checkRegistered['data'];

        $paymenChannel = $paydisiniSettings->payment_channel;
        $keyboards = Keyboard::make()
            ->inline();
        $listChannel = [];
        foreach ($paymenChannel as $channel) {
            $listChannel[] = Keyboard::inlineButton([
                'text' => $channel['name'],
                'callback_data' => 'deposit_' . $channel['id'] . '_' . $amount
            ]);
        }
        $listChannel = array_chunk($listChannel, 2);
        foreach ($listChannel as $row) {
            $keyboards->row($row);
        }

        $telegramService->sendMessage(
            $telegramId,
            <<<EOD
            👋 Selamat datang di {$teleSettings->store_name}
            
            Untuk melakukan deposit sejumlah Rp. {$amount},
            Silahkan pilih channel pembayaran deposit:
            EOD,
            button: $keyboards
        );
    }
}
