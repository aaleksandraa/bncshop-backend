<?php

namespace App\Services\Sync;

class ImportJobFieldLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'name' => 'Naziv',
        'slug' => 'Slug',
        'description' => 'Opis',
        'short_description' => 'Kratak opis',
        'barcode' => 'Barkod',
        'sku' => 'SKU',
        'eline_sifra' => 'eLine šifra',
        'category_id' => 'Kategorija',
        'manufacturer_id' => 'Proizvođač',
        'is_gaming' => 'Gaming',
        'is_public' => 'Javno vidljiv',
        'is_new' => 'Novo',
        'is_refurbished' => 'Refurbished',
        'margin_percentage' => 'Marža',
        'api_price' => 'API cijena',
        'api_final_price' => 'API finalna cijena',
        'regular_price' => 'Redovna cijena',
        'display_price' => 'Prikazna cijena',
        'api_stock' => 'API zaliha',
        'available_stock' => 'Dostupna zaliha',
        'stock_status' => 'Status zalihe',
        'status' => 'Status proizvoda',
        'api_rebate' => 'API popust',
        'api_rebate_valid_until' => 'Popust važi do',
        'api_rebate_type' => 'Tip popusta',
        'attributes' => 'Atributi',
        'images' => 'Slike',
        'supplier_offers' => 'Dobavljačke ponude',
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    /**
     * @param  list<string>  $fields
     */
    public static function formatList(array $fields): string
    {
        if ($fields === []) {
            return '—';
        }

        return implode(', ', array_map(self::label(...), $fields));
    }
}
