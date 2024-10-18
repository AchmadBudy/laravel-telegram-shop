<?php

namespace App\Filament\Resources\DepositHistoryResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\DepositHistoryResource;
use App\Models\DepositHistory;
use App\Models\TelegramUser;
use App\Services\PaydisiniService;
use App\Services\TelegramService;
use App\Settings\PaydisiniSettings;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Telegram\Bot\FileUpload\InputFile;

class ListDepositHistories extends ListRecords
{
    protected static string $resource = DepositHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
            Actions\Action::make('giveDeposit')
                ->label('Give Deposit')
                ->icon('heroicon-o-currency-dollar')
                ->form([
                    Section::make()
                        ->schema([
                            Select::make('telegram_user_id')
                                ->label('Telegram User')
                                ->options(TelegramUser::all()->pluck('telegram_id', 'id')),
                            TextInput::make('total_deposit')
                                ->label('Total Deposit')
                                ->numeric(),
                        ]),
                ])
                ->action(function (array $data) {
                    DB::beginTransaction();
                    try {
                        // get telegram user
                        $paydisiniService = new PaydisiniService();
                        $telegramService = new TelegramService();
                        $telegramUser = TelegramUser::lockforupdate()->find($data['telegram_user_id']);
                        // create record in deposithistory
                        $depositHistory = DepositHistory::create([
                            'telegram_user_id' => $telegramUser->id,
                            'total_deposit' => $data['total_deposit'],
                            'status' => OrderStatus::SUCCESS,
                            'payment_type' => 'DEPOSIT BY ADMIN',
                        ]);

                        // create transaction code
                        $transactionCode = 'DEPOSIT-' .  Str::padLeft($depositHistory->id, 15, '0');

                        // update message id
                        $depositHistory->update([
                            'payment_number' => $transactionCode,
                        ]);

                        // update saldo user
                        $telegramUser->update([
                            'balance' => $telegramUser->balance + $data['total_deposit'],
                        ]);

                        // make message for user
                        $message = "Deposit Rp." . number_format($depositHistory->total_deposit, 0, ',', '.') . " berhasil, saldo anda sekarang Rp " . number_format($telegramUser->balance, 0, ',', '.');

                        // send message to user
                        $telegramService->sendMessage($telegramUser->telegram_id, $message);


                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        Notification::make()
                            ->warning()
                            ->title('Failed to cancel transaction')
                            ->body($th->getMessage())
                            ->send();
                    }

                    Notification::make()
                        ->success()
                        ->title('Deposit success')
                        ->body("Deposit Rp." . number_format($depositHistory->total_deposit, 0, ',', '.') . " berhasil ke user " . $telegramUser->telegram_id)
                        ->send();
                }),
        ];
    }
}
