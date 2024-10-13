<?php

namespace App\Filament\Pages;

use App\Settings\TelegramSettings;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Str;

class ManageTelegram extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = TelegramSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('token')
                            ->password()
                            ->revealable()
                            ->required()
                            ->label('Token'),
                        TextInput::make('random_string')
                            ->label('Random String Webhook')
                            ->required()
                            ->readOnly()
                            ->suffixAction(
                                Action::make('generateRandomString')
                                    ->icon('heroicon-m-arrow-path')
                                    ->label('Generate')
                                    ->action(function (Set $set, $state) {
                                        $set('random_string', Str::random(32));
                                    })
                            ),
                        TextInput::make('bot_username')
                            ->required()
                            ->label('Bot Username'),
                        TextInput::make('owner_username')
                            ->required()
                            ->label('Owner Username'),
                        TextInput::make('bot_url')
                            ->required()
                            ->label('Bot URL'),
                        TextInput::make('store_name')
                            ->required()
                            ->label('Store Name')
                    ])
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionsAction::make('setupWebhook')
                ->label('Setup Webhook')
                ->action(function (TelegramSettings $settings): void {
                    // Your logic here
                    $urlWebhook = url('api/telegram/webhook/' . $settings->random_string);

                    // call service to update webhook
                    $response = (new \App\Services\TelegramService())->setWebhook($urlWebhook);

                    if (!$response) {
                        // call failureNotification
                        Notification::make()
                            ->danger()
                            ->title('Webhook Update Failed')
                            ->body('The webhook could not be updated.')
                            ->send();
                        return;
                    }

                    // call successNotification or failureNotification
                    Notification::make()
                        ->success()
                        ->title('Webhook Updated')
                        ->body("The webhook has been updated successfully.")
                        ->send();
                }),
        ];
    }
}
