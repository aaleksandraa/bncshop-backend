<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\BlogPostResource\Pages\Concerns\ManagesBlogPostLifecycle;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    use ManagesBlogPostLifecycle;

    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewOnSite')
                ->label('Pogledaj na webu')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $this->record->frontendUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->slug)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareBlogPostData($data);
    }
}
