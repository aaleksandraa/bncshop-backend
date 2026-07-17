<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->publicName(),
            'source_name' => $this->name,
            'display_name' => $this->display_name,
            'full_slug' => $this->full_slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image_url' => $this->image_url,
            'icon_url' => $this->icon_url,
            'parent_id' => $this->parent_id,
            'depth' => $this->depth,
            'seo' => $this->whenLoaded('seo', fn () => $this->seo ? [
                'meta_title' => $this->seo->meta_title,
                'meta_description' => $this->seo->meta_description,
                'og_image_url' => $this->seo->og_image_url,
                'h1' => $this->seo->h1,
                'intro_text' => $this->seo->intro_text,
                'footer_text' => $this->seo->footer_text,
            ] : null),
            'children' => CategoryTreeResource::collection($this->whenLoaded('children')),
        ];
    }
}
