<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\BlogPostResource\Pages\Concerns\ManagesBlogPostLifecycle;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    use ManagesBlogPostLifecycle;

    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareBlogPostData($data);
    }
}
