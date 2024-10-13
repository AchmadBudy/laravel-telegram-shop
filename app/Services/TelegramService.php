<?php

namespace App\Services;

use App\Settings\TelegramSettings;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Commands\HelpCommand;

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

        $this->queries = [];
        $this->context = [];
        $this->commands = [
            HelpCommand::class,
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
     * Register command
     * 
     * @return array
     */
    public function registerCommand($idUser, $username, $firstName, $lastName): array
    {
        return [];
    }
}
