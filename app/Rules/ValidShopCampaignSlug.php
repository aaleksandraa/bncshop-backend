<?php

namespace App\Rules;

use App\Models\CmsPage;
use App\Models\ShopCampaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidShopCampaignSlug implements ValidationRule
{
    /**
     * @var list<string>
     */
    public const RESERVED = [
        'akcija',
        'b2b',
        'blog',
        'brendovi',
        'checkout',
        'kategorije',
        'korpa',
        'kupovina-na-rate',
        'lista-zelja',
        'nalog',
        'narudzba',
        'novo',
        'pretraga',
        'prodavac',
        'refurbished',
        'servis',
        'stranica',
        'uporedi',
    ];

    public function __construct(
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = is_string($value) ? trim($value) : '';

        if ($slug === '') {
            $fail('Slug je obavezan.');

            return;
        }

        if (in_array($slug, self::RESERVED, true)) {
            $fail('Ovaj slug je rezervisan za sistemsku stranicu.');

            return;
        }

        if (CmsPage::query()->where('slug', $slug)->exists()) {
            $fail('Slug već postoji kao CMS stranica.');

            return;
        }

        $query = ShopCampaign::query()->where('slug', $slug);

        if ($this->ignoreId !== null) {
            $query->whereKeyNot($this->ignoreId);
        }

        if ($query->exists()) {
            $fail('Slug već postoji.');
        }
    }
}
