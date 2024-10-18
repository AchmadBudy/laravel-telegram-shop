<?php

namespace App\Filament\Resources\DepositHistoryResource\Pages;

use App\Filament\Resources\DepositHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepositHistory extends EditRecord
{
    protected static string $resource = DepositHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
