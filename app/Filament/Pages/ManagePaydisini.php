<?php

namespace App\Filament\Pages;

use App\Services\PaydisiniService;
use App\Settings\PaydisiniSettings;
use Filament\Actions;
use Filament\Forms;
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
        $this->listChannelPayment = collect([]);
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $settings = app(static::getSettings());


        // call the method from PaydisiniService
        // $paydisiniService = new PaydisiniService();


        $data = $this->mutateFormDataBeforeFill($settings->toArray());

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('api_key')
                            ->label('API Key')
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
                                Select::make('channel_id')
                                    ->label('Channel')
                                    ->options(
                                        $this->listChannelPayment
                                            ->pluck('name', 'id')
                                    )
                                    ->distinct()
                                    ->reactive()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $channelId = $get('channel_id');
                                        $set('channel_name', $this->listChannelPayment->where('id', $channelId)->first()['name']);
                                    })
                                    ->searchable()
                                    ->required(),
                                TextInput::make('channel_name')
                                    ->label('Channel Name')
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
