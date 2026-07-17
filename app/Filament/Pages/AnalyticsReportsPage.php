<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\CanAccessAnalytics;
use App\Services\Analytics\AnalyticsService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class AnalyticsReportsPage extends Page implements HasForms
{
    use CanAccessAnalytics;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Analitika';

    protected static ?string $navigationLabel = 'Izvještaji';

    protected static ?string $title = 'Analitički izvještaji';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.analytics-reports';

    public ?string $activeTab = 'period';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date_from')
                    ->label('Od')
                    ->required()
                    ->native(false),
                DatePicker::make('date_to')
                    ->label('Do')
                    ->required()
                    ->native(false),
            ])
            ->columns(2)
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPeriodReport(): array
    {
        $from = Carbon::parse($this->filters['date_from'] ?? now()->subDays(30));
        $to = Carbon::parse($this->filters['date_to'] ?? now())->endOfDay();

        return app(AnalyticsService::class)->salesReport($from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardExtras(): array
    {
        return app(AnalyticsService::class)->dashboardKpis();
    }

    public static function canAccess(): bool
    {
        return static::canAccessAnalytics();
    }
}
