<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case CANCELBYUSER = 'cancel_by_user';
    case CANCELBYADMIN = 'cancel_by_admin';
    case CANCELBYTIMEOUT = 'cancel_by_timeout';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUCCESS => 'Success',
            self::CANCELBYUSER => 'Cancel by user',
            self::CANCELBYADMIN => 'Cancel by admin',
            self::CANCELBYTIMEOUT => 'Cancel by timeout',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SUCCESS => 'green',
            self::CANCELBYUSER => 'red',
            self::CANCELBYADMIN => 'red',
            self::CANCELBYTIMEOUT => 'red',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::SUCCESS => 'heroicon-o-check-circle',
            self::CANCELBYUSER => 'heroicon-o-x-circle',
            self::CANCELBYADMIN => 'heroicon-o-x-circle',
            self::CANCELBYTIMEOUT => 'heroicon-o-x-circle',
        };
    }
}
