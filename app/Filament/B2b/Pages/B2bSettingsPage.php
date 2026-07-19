<?php

namespace App\Filament\B2b\Pages;

use App\Models\B2bSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class B2bSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Postavke';

    protected static ?string $navigationLabel = 'B2B postavke';

    protected static ?string $title = 'B2B postavke';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.b2b.pages.b2b-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return $user->can('b2b_settings.view') || $user->can('b2b_settings.update');
    }

    public function mount(): void
    {
        $settings = B2bSetting::instance();

        $this->form->fill([
            'default_customer_discount_percent' => $settings->default_customer_discount_percent,
            'admin_notification_email' => $settings->admin_notification_email,
            'notify_customers_on_new_product' => $settings->notify_customers_on_new_product,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Popusti i notifikacije')->schema([
                    TextInput::make('default_customer_discount_percent')
                        ->label('Default popust kupca (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->required(),
                    Placeholder::make('mail_from_info')
                        ->label('Pošiljalac (From)')
                        ->content(fn (): string => config('b2b.mail.from_name').' <'.config('b2b.mail.from_address').'>')
                        ->helperText('Konfigurisano na serveru (.env). Admin može mijenjati samo primatelja obavijesti.'),
                    TextInput::make('admin_notification_email')
                        ->label('Email za B2B notifikacije')
                        ->email()
                        ->maxLength(255)
                        ->placeholder('b2b@bncshop.ba')
                        ->helperText('Primatelj B2B obavijesti o narudžbama i zahtjevima za pristup.'),
                    Toggle::make('notify_customers_on_new_product')
                        ->label('Email kupcima o novim proizvodima')
                        ->helperText('Šalje plain-text email aktivnim B2B kupcima kad se doda ili aktivira proizvod (grupisano u digest).'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = B2bSetting::instance();
        $settings->update($data);

        Notification::make()->title('Postavke sačuvane.')->success()->send();
    }
}
