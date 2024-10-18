<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;

/**
 * This command can be triggered in two ways:
 * /start and /register due to the alias.
 */
class StartCommand extends Command
{
    protected string $name = 'start';
    protected array $aliases  = ['register'];
    protected string $description = 'command to register user';

    public function handle()
    {
        $username = $this->getUpdate()->getMessage()->from->username;
        $idUser = $this->getUpdate()->getMessage()->from->id;
        $firstName = $this->getUpdate()->getMessage()->from->firstName;
        $lastName = $this->getUpdate()->getMessage()->from->lastName;

        $teleService = new TelegramService();
        $teleService->registerCommand($idUser, $username, $firstName, $lastName);
    }
}
