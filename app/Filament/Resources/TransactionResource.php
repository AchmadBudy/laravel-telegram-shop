<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\TelegramService;
use App\Settings\PaydisiniSettings;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

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
                        TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $totalPrice = $get('unit_price') ?? 0;
                                $discount = $state;
                                $finalPrice = $totalPrice - $discount;
                                if ($finalPrice < 0) {
                                    $set('discount', $totalPrice);
                                    $set('total_price', 0);
                                } else {
                                    $set('total_price', $finalPrice);
                                }
                            }),
                        TextInput::make('total_price')
                            ->label('Total Price')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(collect((new PaydisiniSettings())->payment_channel)->pluck('name', 'id'))
                            ->required(),
                    ]),
                Section::make()
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
                                $discount = $get('discount') ?? 0;
                                $unitPrice = $price * $quantity;
                                $finalPrice = $unitPrice - $discount;
                                $set('unit_price', $unitPrice);
                                if ($finalPrice < 0) {
                                    $set('discount', $unitPrice);
                                    $set('total_price', 0);
                                } else {
                                    $set('total_price', $finalPrice);
                                }
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
                            ->default(0)
                            ->columnSpanFull()
                            ->required(),
                    ])
                    ->visibleOn('create')
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
                TextColumn::make('total_price')
                    ->label('Total Price')
                    ->money('IDR')
                    ->searchable(),
                TextColumn::make('payment_type')
                    ->label('Payment Type')
                    ->searchable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->requiresConfirmation()
                    ->action(function (Transaction $records): void {
                        $paymentService = new PaymentService();
                        $telegramService = new TelegramService();
                        // telgeram user
                        try {
                            $response = $paymentService->cancelPayment($records->payment_number, cancelByAdmin: true);
                            if (!$response['success']) {
                                throw new \Exception($response['message']);
                            }
                            if ($response['data']->message_id) {
                                $telegramService->deleteMessage($response['telegram']->telegram_id, $response['data']->message_id);
                            }

                            $telegramService->sendMessage($response['telegram']->telegram_id, 'Pembayaran telah dibatalkan.');
                        } catch (\Exception $e) {
                            Notification::make()
                                ->warning()
                                ->title('Failed to cancel transaction')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn(Transaction $record) => $record->status === OrderStatus::PENDING->value),
                Tables\Actions\Action::make('resendItem')
                    ->label('Resend Item')
                    ->requiresConfirmation()
                    ->visible(fn(Transaction $record) => $record->status === OrderStatus::SUCCESS->value)
                    ->action(function (Transaction $records): void {
                        $paymentService = new PaymentService();
                        $telegramService = new TelegramService();
                        // telgeram user
                        try {
                            $response = $paymentService->getItemFromTransaction($records->payment_number);
                            if (!$response['success']) {
                                Log::info($response);

                                throw new \Exception($response['message']);
                            }
                            Log::info($response);
                            // send new message but check if isFile is true or false
                            if ($response['isFile']) {
                                $telegramService->telegram->sendDocument([
                                    'chat_id' => $response['telegram']->telegram_id,
                                    'document' => $response['file_path'],
                                    'caption' => $response['messageToUser'],
                                    'parse_mode' => 'Markdown',
                                ]);
                            } else {
                                $telegramService->sendMessage($response['telegram']->telegram_id, $response['messageToUser']);
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->warning()
                                ->title('Failed to resend item')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            // 'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    protected function cannEdit(): bool
    {
        return false;
    }
}
