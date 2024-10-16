<?php

namespace App\Filament\Pages;

use App\Services\PaydisiniService;
use App\Settings\PaydisiniSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;

class ManagePaydisini extends SettingsPage
{
    // use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = PaydisiniSettings::class;

    protected static ?string $navigationGroup = 'Settings';

    public $listChannelPayment;

    public function __construct()
    {
        $this->listChannelPayment = collect(
            (new PaydisiniSettings())->payment_channel
        );
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $settings = app(static::getSettings());

        $data = $this->mutateFormDataBeforeFill($settings->toArray());
        $data['webhook_url'] = url('/api/paydisini/webhook/response');
        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('webhook_url')
                            ->label('Webhook For Paydisini')
                            ->default(url('/api/paydisini/webhook/response'))
                            ->disabled()
                            ->suffixAction(
                                Action::make('copy')
                                    ->icon('heroicon-s-clipboard-document-check')
                                    ->action(function ($livewire, $state) {
                                        $livewire->js(
                                            'window.navigator.clipboard.writeText("' . $state . '");
                                            $tooltip("' . __('Copied to clipboard') . '", { timeout: 1500 });'
                                        );
                                    })

                            ),
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Enter your API Key')
                            ->required(),
                        Select::make('fee_type')
                            ->label('Fee Type')
                            ->options([
                                '1' => 'fee ditanggung customer',
                                '2' => 'fee ditanggung merchant',
                            ])
                            ->required(),

                        Repeater::make('payment_channel')
                            ->schema([
                                Select::make('id')
                                    ->label('Channel')
                                    ->options(
                                        $this->listChannelPayment
                                            ->pluck('name', 'id')
                                    )
                                    ->distinct()
                                    ->reactive()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $channelId = $get('id');
                                        $set('name', $this->listChannelPayment->where('id', $channelId)->first()['name']);
                                        $set('fee', $this->listChannelPayment->where('id', $channelId)->first()['fee']);
                                        $set('minimum', $this->listChannelPayment->where('id', $channelId)->first()['minimum']);
                                    })
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Channel Name')
                                    ->readOnly()
                                    ->required(),
                                TextInput::make('fee')
                                    ->label('Channel Fee')
                                    ->readOnly()
                                    ->required(),
                                TextInput::make('minimum')
                                    ->label('Channel Minimal Payment')
                                    ->readOnly()
                                    ->required(),
                            ])
                    ])
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('setupPaymentChannel')
                ->label('Setup Payment Channel')
                ->action(function (PaydisiniSettings $settings): void {
                    $paydisiniService = new PaydisiniService();
                    $response = $paydisiniService->showChannelPembayaran();

                    if (!$response['success']) {
                        Notification::make()
                            ->danger()
                            ->title('Payment Channel Update Failed')
                            ->body($response['msg'])
                            ->send();
                        return;
                    }

                    $this->listChannelPayment = collect($response['data']);

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Payment Channel Updated')
                        ->body('The payment channel has been updated.')
                        ->send();
                }),
        ];
    }
}
