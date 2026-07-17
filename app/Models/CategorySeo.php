<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySeo extends Model
{
    protected $table = 'category_seo';

    protected $fillable = [
        'category_id',
        'meta_title',
        'meta_description',
        'og_image_url',
        'h1',
        'intro_text',
        'footer_text',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
