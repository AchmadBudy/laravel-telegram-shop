<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Settings\TelegramSettings;
use App\Telegram\Commands\DepositCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Context\CaraOrderContext;
use App\Telegram\Context\CariContext;
use App\Telegram\Context\DepositContext;
use App\Telegram\Context\InformationContext;
use App\Telegram\Context\StockContext;
use App\Telegram\Queries\CancelPaymentQuery;
use App\Telegram\Queries\CheckPaymentQuery;
use App\Telegram\Queries\DepositQuery;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Commands\HelpCommand;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramService
{
    public $telegram;
    public $queries;
    public $context;
    public $commands;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $teleSettings = new TelegramSettings();

        $this->queries = [
            CancelPaymentQuery::class,
            CheckPaymentQuery::class,
            DepositQuery::class
        ];
        $this->context = [
            InformationContext::class,
            CaraOrderContext::class,
            StockContext::class,
            DepositContext::class,
            CariContext::class
        ];
        $this->commands = [
            HelpCommand::class,
            StartCommand::class,
            DepositCommand::class,
        ];

        $config = [
            'bots' => [
                'mybot' => [
                    'token' => $teleSettings->token,
                    'webhook_url' => url('api/telegram/webhook/' . $teleSettings->random_string),
                    'commands' => $this->commands,
                    'queries' => $this->queries,
                    'context' => $this->context,
                ]
            ]
        ];

        $botManager = new BotsManager($config);
        $this->telegram = $botManager->bot('mybot');
    }

    /**
     * Set webhook
     *
     * @param string $url
     * @return bool
     */
    public function setWebhook($url): bool
    {
        try {
            $response = $this->telegram->setWebhook(['url' => $url]);
            return $response;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get webhook info
     *
     * @return array
     */
    public function getWebhookInfo(): array
    {
        try {
            $response = $this->telegram->getWebhookInfo();
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send message
     * 
     * @param string $chatId
     * @param string $message
     * @param string $parseMode
     * 
     * @return array
     */
    public function sendMessage(string $chatId, string $message, string $parseMode = 'Markdown', null|string $button = null): array
    {
        try {
            $message = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ];

            if ($button) {
                $message['reply_markup'] = $button;
            }

            $this->telegram->sendMessage($message);
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Delete message
     * 
     * @param string $chatId
     * @param int $messageId
     *
     * @return array
     */
    public function deleteMessage(string $chatId, int $messageId): array
    {
        try {
            $this->telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }

        return [
            'success' => true,
        ];
    }


    /**
     * Check if user is registered
     */
    public function checkRegistered($idUser): array
    {
        $user = TelegramUser::where('telegram_id', $idUser)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not registered'
            ];
        }

        return [
            'success' => true,
            'data' => $user
        ];
    }

    /**
     * Register command
     * 
     * @return array
     */
    public function registerCommand($idUser, $username, $firstName, $lastName): array
    {
        // get telegram setting
        $teleSettings = new TelegramSettings();
        // get user if exist
        $user = TelegramUser::where('telegram_id', $idUser)->first();

        $button = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->row([
                Keyboard::button(['text' => '🛒 Stock']),
                Keyboard::button(['text' => '📝 Cara Order']),
            ])
            ->row([
                Keyboard::button(['text' => 'Informasi']),
                Keyboard::button(['text' => 'Cara Deposit']),
            ]);

        if ($user) {
            return $this->sendMessage(
                $idUser,
                <<<EOD
                👋 Selamat datang di {$teleSettings->store_name}
                ─────〔 DATA USER 〕─────
                🆔 ID: {$idUser}
                🧑 Nama: {$firstName} {$lastName}
                📊 Saldo: Rp. {$user->balance}
                username: {$username}

                📝 Anda sudah terdaftar di sistem kami.
                Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
                EOD,
                button: $button
            );
        }

        // register user
        $user = TelegramUser::create([
            'telegram_id' => $idUser,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'balance' => 0,
        ]);

        return $this->sendMessage(
            $idUser,
            <<<EOD
            👋 Selamat datang di {$teleSettings->store_name}
            ─────〔 DATA USER 〕─────
            🆔 ID: {$idUser}
            🧑 Nama: {$firstName} {$lastName}
            📊 Saldo: Rp. {$user->balance}
            username: {$username}

            📝 Anda berhasil terdaftar di sistem kami.
            Jika ada pertanyaan, silahkan hubungi admin. @{$teleSettings->owner_username}
            EOD,
            button: $button
        );
    }

    /**
     * Escape markdown v2
     * 
     * @param string $text
     * @return string
     */
    public static function escapeMarkdownV2($text)
    {
        $specialChars = ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }

    /**
     * Escape markdown
     * 
     * @param string $text
     * @return string
     */
    public static function escapeMarkdown($text)
    {
        $specialChars = ['_', '*', '`', '['];
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
}
