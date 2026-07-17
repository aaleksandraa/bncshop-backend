<?php

namespace App\Filament\Resources\BlogPostResource\Pages\Concerns;

trait ManagesBlogPostLifecycle
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareBlogPostData(array $data): array
    {
        $data['author_id'] ??= auth()->id();

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (filled($data['featured_image_path'] ?? null)) {
            $data['featured_image_url'] = null;
        }

        return $data;
    }
}
