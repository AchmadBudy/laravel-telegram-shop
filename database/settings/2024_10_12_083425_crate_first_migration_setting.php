<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('telegram.token', 'YOUR_TELEGRAM_BOT_TOKEN');
        $this->migrator->add('telegram.random_string', '');
        $this->migrator->add('telegram.bot_username', '');
        $this->migrator->add('telegram.bot_url', '');
        $this->migrator->add('telegram.store_name', '');
    }
};
