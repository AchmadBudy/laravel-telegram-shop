<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Settings\TelegramSettings;
use App\Telegram\Commands\DepositCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Context\CaraOrderContext;
use App\Telegram\Context\CariContext;
use App\Telegram\Context\DepositContext;
use App\Telegram\Context\InformationContext;
use App\Telegram\Context\StockContext;
use App\Telegram\Queries\CancelPaymentQuery;
use App\Telegram\Queries\CancelProductQuery;
use App\Telegram\Queries\CheckoutProductQuery;
use App\Telegram\Queries\CheckPaymentQuery;
use App\Telegram\Queries\ContinueProductQuery;
use App\Telegram\Queries\DecreamentProductQuery;
use App\Telegram\Queries\DepositQuery;
use App\Telegram\Queries\IncreamentProductQuery;
use App\Telegram\Queries\OrderProductQuery;
use Illuminate\Support\Facades\DB;
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
            DepositQuery::class,
            OrderProductQuery::class,
            IncreamentProductQuery::class,
            DecreamentProductQuery::class,
            CancelProductQuery::class,
            ContinueProductQuery::class,
            CheckoutProductQuery::class,
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

            $response  = $this->telegram->sendMessage($message);
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }

        return [
            'success' => true,
            'message_id' => $response->message_id
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
     * Show Order product
     * 
     * @param string $idProduct
     * 
     * @return array
     */
    public function showOrderProduct(string $idProduct, int|null $amount = null, bool $decreament = false, bool $increament = false): array
    {
        try {
            // find product
            $product = Product::query()
                ->active()
                ->where('id', $idProduct)
                ->first();

            if (!$product) {
                throw new \Exception('Product tidak ditemukan/tidak aktif');
            }

            if ($amount === null) {
                $amount = 1;
            }

            if ($decreament) {
                $amount = $amount - 1;
            } elseif ($increament) {
                $amount = $amount + 1;
            }

            // check amount
            if ($amount < 1) {
                throw new \Exception('Jumlah tidak valid');
            }

            // check stock
            if ($product->stock < $amount) {
                throw new \Exception('Stock tidak cukup');
            }

            // create beli semua amount
            if ($product->stock > 2) {
                $amountAll = $product->stock - 1;
            } else {
                $amountAll = $product->stock;
            }

            $keyboard = Keyboard::make()
                ->inline()
                ->row([
                    Keyboard::inlineButton([
                        'text' => '➖',
                        'callback_data' => 'decreament_product_' . $product->id . '_' . $amount
                    ]),
                    Keyboard::inlineButton([
                        'text' => 'Beli Semua',
                        'callback_data' => 'continue_order_' . $product->id . '_' . $amountAll
                    ]),
                    Keyboard::inlineButton([
                        'text' => '➕',
                        'callback_data' => 'increament_product_' . $product->id . '_' . $amount
                    ]),
                ])
                ->row([
                    Keyboard::inlineButton([
                        'text' => 'Cancel',
                        'callback_data' => 'cancel_order'
                    ]),
                ])
                ->row([
                    Keyboard::inlineButton([
                        'text' => 'Order',
                        'callback_data' => 'continue_order_' . $product->id . '_' . $amount
                    ]),
                ]);
            $totalPrice = $product->price * $amount;
            $message = <<<EOD
            ==========================
            📦 *{$product->name}*
            💰 Rp. {$product->price}
            📝 {$product->description}
            ==========================
            Jumlah: {$amount}
            Total: Rp. {$totalPrice}
            ==========================
            EOD;
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }


        return [
            'success' => true,
            'data' => [
                'message' => $message,
                'keyboard' => $keyboard
            ]
        ];
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
