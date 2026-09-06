<?php

namespace App\Filament\Pages;

use App\Services\Integrations\MetaCatalogFeedService;
use App\Services\Integrations\TrackingSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TrackingSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Analitika i kolačići';

    protected static ?string $title = 'Google Analytics, Meta Pixel i saglasnost';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.tracking-settings';

    public ?array $data = [];

    public string $metaCatalogFeedUrl = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('customers.update'));
    }

    public function mount(TrackingSettings $settings, MetaCatalogFeedService $catalogFeed): void
    {
        $this->form->fill($settings->all());
        $this->metaCatalogFeedUrl = $catalogFeed->feedUrl();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Banner saglasnosti (prva posjeta)')
                    ->schema([
                        Toggle::make('consent_enabled')
                            ->label('Prikaži banner za kolačiće')
                            ->default(true),
                        TextInput::make('consent_title')
                            ->label('Naslov bannera')
                            ->maxLength(255),
                        Textarea::make('consent_message')
                            ->label('Tekst bannera')
                            ->rows(4),
                        TextInput::make('privacy_page_slug')
                            ->label('Slug stranice privatnosti')
                            ->helperText('Npr. privatnost → /stranica/privatnost')
                            ->maxLength(120),
                        Toggle::make('load_scripts_only_with_consent')
                            ->label('Učitaj GA/Pixel tek nakon prihvatanja')
                            ->default(true),
                    ])
                    ->columns(1),
                Section::make('Praćenje (javni ID-evi)')
                    ->description('Measurement ID i Pixel ID su javni. Purchase se šalje i sa servera (API secret) da narudžbe uđu u Analytics i kad kupac odbije neobavezne kolačiće.')
                    ->schema([
                        TextInput::make('ga_measurement_id')
                            ->label('Google Analytics 4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(32),
                        TextInput::make('ga_api_secret')
                            ->label('GA4 Measurement Protocol API secret')
                            ->password()
                            ->revealable()
                            ->helperText('Admin → Data streams → Measurement Protocol API secrets. Purchase ide u Analytics i kad kupac odbije neobavezne kolačiće. Ne prikazuje se na shopu.')
                            ->maxLength(64),
                        TextInput::make('fb_pixel_id')
                            ->label('Meta (Facebook) Pixel ID')
                            ->placeholder('786294308773690')
                            ->helperText('Obavezno za PageView i ViewContent u browseru. Ako ostane prazno, koristi se Dataset ID iz CAPI sekcije.')
                            ->maxLength(32),
                    ])
                    ->columns(2),
                Section::make('Meta Conversions API (server)')
                    ->description('Dataset ID i access token za server-side događaje (Purchase, Lead). Purchase se šalje pri narudžbi; Lead pri upitu za rate. Koristi isti dataset kao Pixel u Events Manageru.')
                    ->schema([
                        TextInput::make('fb_dataset_id')
                            ->label('Meta Dataset ID')
                            ->placeholder('786294308773690')
                            ->helperText('Ako ostane prazno, koristi se Pixel ID.')
                            ->maxLength(32),
                        TextInput::make('fb_access_token')
                            ->label('Meta Conversions API access token')
                            ->password()
                            ->revealable()
                            ->helperText('Events Manager → Dataset → Settings → Generate access token. Ne prikazuje se na shopu.')
                            ->maxLength(512),
                        TextInput::make('fb_test_event_code')
                            ->label('Test event code (opcionalno)')
                            ->helperText('Dok je popunjeno, SVI CAPI događaji (uključujući Purchase) idu samo u Test Events tab — ne u glavni Overview. Obriši nakon testiranja.')
                            ->maxLength(64),
                        TextInput::make('fb_crm_name')
                            ->label('CRM naziv (lead_event_source)')
                            ->default('BNC Shop')
                            ->helperText('Prikazuje se u Meta CRM integraciji za Lead događaje.')
                            ->maxLength(120),
                    ])
                    ->columns(2),
                Section::make('Meta Product Catalog (Facebook Shop)')
                    ->description('CSV feed za Commerce Manager. Samo proizvodi na stanju. Slike idu preko CDN-a (images.bnc.ba). Periferija tipa zaštitna stakla se filtrira po ključnim riječima u nazivu.')
                    ->schema([
                        Placeholder::make('meta_catalog_feed_url')
                            ->label('CSV feed URL')
                            ->content(fn (): string => $this->metaCatalogFeedUrl),
                        Textarea::make('fb_catalog_include_category_slugs')
                            ->label('Uključi kategorije (full_slug, jedan po liniji)')
                            ->rows(8)
                            ->helperText('Prazno = default iz config/bnc.php (računari, laptopi, monitori, miševi, tastature, klime, printeri). Primjer: it-oprema/laptopi'),
                        Textarea::make('fb_catalog_exclude_category_slugs')
                            ->label('Isključi kategorije (full_slug, jedan po liniji)')
                            ->rows(4)
                            ->helperText('Opcionalno. Npr. telefonija, it-oprema/periferija/mobiteli-dodaci'),
                        Textarea::make('fb_catalog_exclude_name_keywords')
                            ->label('Isključi proizvode po riječima u nazivu')
                            ->rows(4)
                            ->helperText('Dodatno na default: zaštitno staklo, folija, maskica… (neovisno o kategoriji).'),
                        Placeholder::make('meta_catalog_setup')
                            ->label('Koraci u Commerce Manager')
                            ->content("1. Catalog → Data sources → + Add → Data feed (CSV)\n2. Scheduled feed → URL iznad\n3. Currency: BAM, trusted domain: bnc.ba\n4. Server env: BNC_MEDIA_ORIGIN=https://images.bnc.ba\n5. php artisan meta:catalog-stats --check-images\n6. php artisan meta:diagnose — CAPI test"),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(TrackingSettings $settings, MetaCatalogFeedService $catalogFeed): void
    {
        $settings->save($this->form->getState());
        $this->metaCatalogFeedUrl = $catalogFeed->feedUrl();

        Notification::make()
            ->title('Postavke analitike sačuvane')
            ->success()
            ->send();
    }
}
