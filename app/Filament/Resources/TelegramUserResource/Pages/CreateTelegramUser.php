<?php

namespace App\Filament\Resources\TelegramUserResource\Pages;

use App\Filament\Resources\TelegramUserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTelegramUser extends CreateRecord
{
    protected static string $resource = TelegramUserResource::class;
}
