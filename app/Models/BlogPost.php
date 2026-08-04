<?php

namespace App\Models;

use App\Services\Media\MediaStorage;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'featured_image_url',
        'featured_image_path',
        'storage_disk',
        'intro',
        'content_blocks',
        'status',
        'published_at',
        'author_id',
        'meta_title',
        'meta_description',
        'og_image_url',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'published_at' => 'datetime',
            'optimized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (BlogPost $post): void {
            if ($post->featured_image_path) {
                app(MediaStorage::class)->deleteFromAnyDisk(
                    $post->featured_image_path,
                    $post->storage_disk,
                );
            }
        });

        static::saving(function (BlogPost $post): void {
            if ($post->isDirty('featured_image_path') && filled($post->featured_image_path)) {
                $post->storage_disk = app(MediaStorage::class)->diskName();
                $post->optimized_at = now();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return $this->published_at === null || $this->published_at->lte(now());
    }

    public function featuredImageUrl(): ?string
    {
        if (filled($this->featured_image_path)) {
            return PublicStorageUrl::url($this->featured_image_path);
        }

        return filled($this->featured_image_url) ? (string) $this->featured_image_url : null;
    }

    public function featuredImageAbsoluteUrl(): ?string
    {
        if (filled($this->featured_image_path)) {
            return PublicStorageUrl::absoluteUrl($this->featured_image_path);
        }

        return filled($this->featured_image_url) ? (string) $this->featured_image_url : null;
    }

    public function ogImageUrl(): ?string
    {
        if (filled($this->og_image_url)) {
            return (string) $this->og_image_url;
        }

        return $this->featuredImageAbsoluteUrl();
    }

    public function frontendUrl(): string
    {
        return rtrim((string) config('bnc.frontend_url', 'http://localhost:3000'), '/').'/blog/'.$this->slug;
    }

    public function syncSeoDefaults(): void
    {
        if (blank($this->meta_title) && filled($this->title)) {
            $this->meta_title = (string) $this->title;
        }

        if (blank($this->meta_description)) {
            if (filled($this->excerpt)) {
                $this->meta_description = Str::limit(strip_tags((string) $this->excerpt), 160);
            } elseif (filled($this->intro)) {
                $this->meta_description = Str::limit(strip_tags((string) $this->intro), 160);
            }
        }

        if (blank($this->og_image_url)) {
            $this->og_image_url = $this->featuredImageAbsoluteUrl();
        }
    }
}
