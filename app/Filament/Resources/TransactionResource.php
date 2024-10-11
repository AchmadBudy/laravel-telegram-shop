<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\TelegramUser;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('telegram_user_id')
                            ->label('Telegram User')
                            ->options(TelegramUser::all()->pluck('telegram_id', 'id'))
                            ->searchable(),
                        Select::make('status')
                            ->label('Status')
                            ->options(OrderStatus::class)
                            ->required(),
                    ]),
                Section::make()
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(\App\Models\Product::active()->pluck('name', 'id'))
                                    ->distinct()
                                    ->reactive()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $price = \App\Models\Product::find($state)?->price ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('unit_price', $price * $quantity);
                                    })
                                    ->searchable()
                                    ->columnSpan(1)
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $price = $get('product_id') ? \App\Models\Product::find($get('product_id'))?->price : 0;
                                        $quantity = $state;
                                        $set('unit_price', $price * $quantity);
                                    })
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('unit_price')
                                    ->label('Total Price Product')
                                    ->disabled()
                                    ->dehydrated()
                                    ->numeric()
                                    ->columnSpanFull()
                                    ->required(),
                            ])
                            ->columns(2)
                    ])
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
                    ->color(OrderStatus::class)
                    ->searchable(),
                TextColumn::make('total_price')
                    ->label('Total Price')
                    ->money('IDR')
                    ->searchable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
