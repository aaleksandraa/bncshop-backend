<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\Catalog\ProductReadCache;
use App\Support\SafeHtml;

class BlogPostObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saving(BlogPost $post): void
    {
        if ($post->intro !== null) {
            $post->intro = SafeHtml::clean($post->intro);
        }

        if (is_array($post->content_blocks)) {
            $post->content_blocks = collect($post->content_blocks)
                ->map(function (array $block): array {
                    if (($block['type'] ?? null) !== 'rich_text') {
                        return $block;
                    }

                    $body = $block['data']['body'] ?? null;

                    if (is_string($body)) {
                        $block['data']['body'] = SafeHtml::clean($body);
                    }

                    return $block;
                })
                ->values()
                ->all();
        }

        $post->syncSeoDefaults();
    }

    public function saved(BlogPost $post): void
    {
        $this->productReadCache->forgetBlogPost($post->slug);
        $this->productReadCache->flushBlog();
    }

    public function deleted(BlogPost $post): void
    {
        $this->productReadCache->forgetBlogPost($post->slug);
        $this->productReadCache->flushBlog();
    }
}
