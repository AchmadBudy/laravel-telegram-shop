<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\DepositHistoryResource\Pages;
use App\Filament\Resources\DepositHistoryResource\RelationManagers;
use App\Models\DepositHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DepositHistoryResource extends Resource
{
    protected static ?string $model = DepositHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.telegram_id')
                    ->label('Telegram User')
                    ->searchable(),
                TextColumn::make('payment_number')
                    ->label('Payment Number')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        OrderStatus::PENDING->value => 'warning',
                        OrderStatus::SUCCESS->value => 'success',
                        OrderStatus::CANCELBYUSER->value => 'danger',
                        OrderStatus::CANCELBYADMIN->value => 'danger',
                        OrderStatus::CANCELBYTIMEOUT->value => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        OrderStatus::PENDING->value => 'Pending',
                        OrderStatus::SUCCESS->value => 'Success',
                        OrderStatus::CANCELBYUSER->value => 'Cancel by user',
                        OrderStatus::CANCELBYADMIN->value => 'Cancel by admin',
                        OrderStatus::CANCELBYTIMEOUT->value => 'Cancel by timeout',
                    })
                    ->searchable(),
                TextColumn::make('total_deposit')
                    ->label('Total Deposit')
                    ->money('IDR')
                    ->searchable(),
                TextColumn::make('payment_type')
                    ->label('Payment Type')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->modifyQueryUsing(function (Builder $query): Builder {
                return  $query->orderBy('created_at', 'desc');
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepositHistories::route('/'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
