<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryNavResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->publicName(),
            'full_slug' => $this->full_slug,
            'parent_id' => $this->parent_id,
            'depth' => $this->depth,
            'children' => CategoryNavResource::collection($this->whenLoaded('children')),
        ];
    }
}
