<?php

namespace App\Services\Olx;

use App\Models\Product;
use App\Services\Catalog\AttributeDisplayService;

class OlxDescriptionBuilder
{
    public function __construct(
        private readonly AttributeDisplayService $attributeDisplayService,
        private readonly OlxHtmlFormatter $htmlFormatter,
    ) {}

    public function buildShortDescription(Product $product): string
    {
        $short = trim(strip_tags((string) ($product->short_description ?? '')));

        if ($short !== '') {
            return \Illuminate\Support\Str::limit($short, 500, '');
        }

        $body = trim(strip_tags($this->extractPlainText($product)));

        if ($body === '') {
            return '';
        }

        $intro = preg_split("/\n{2,}/", $body)[0] ?? $body;

        return \Illuminate\Support\Str::limit(trim($intro), 500, '');
    }

    public function buildDescription(Product $product, OlxSyncSettings $settings): string
    {
        $sections = [];

        $short = trim(strip_tags((string) ($product->short_description ?? '')));

        if ($short !== '') {
            $sections[] = $this->htmlFormatter->plainTextToHtml($short);
        }

        $body = $this->formatProductBody($product);

        if ($body !== '') {
            $sections[] = $body;
        }

        $specs = $this->buildSpecificationsSection($product);

        if ($specs !== '') {
            $sections[] = $specs;
        }

        $footer = trim($settings->descriptionFooter());

        if ($footer !== '') {
            $sections[] = $this->htmlFormatter->formatBlock($footer);
        }

        return $this->htmlFormatter->combineSections($sections);
    }

    private function formatProductBody(Product $product): string
    {
        $raw = trim((string) ($product->description ?? ''));

        if ($raw === '') {
            return '';
        }

        return $this->htmlFormatter->formatBlock($raw);
    }

    private function extractPlainText(Product $product): string
    {
        $raw = trim((string) ($product->description ?? ''));

        if ($raw === '') {
            return '';
        }

        if ($this->htmlFormatter->looksLikeHtml($raw)) {
            return trim(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $raw)));
        }

        return $raw;
    }

    private function buildSpecificationsSection(Product $product): string
    {
        $product->loadMissing(['attributeValues.attributeDefinition']);

        $attributes = $this->attributeDisplayService->formatManyForProduct(
            $product->attributeValues,
            $product->category_id,
        );

        return $this->htmlFormatter->specificationsHtml($attributes);
    }
}
