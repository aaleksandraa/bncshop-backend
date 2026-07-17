<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttributeDefinition;
use App\Models\ProductAttributeValue;

$osDefIds = AttributeDefinition::query()
    ->whereIn('name', [
        'Operativni sistem',
        'Operativni Sustav',
        'Operativni sistem - uređaj',
        'Operativni Sustavi',
    ])
    ->pluck('id');

echo "OS definition IDs: ".$osDefIds->implode(', ')."\n\n";

$values = ProductAttributeValue::query()
    ->whereIn('attribute_definition_id', $osDefIds)
    ->with('attributeDefinition')
    ->limit(5000)
    ->get()
    ->map(fn ($v) => trim((string) ($v->display_value ?? $v->normalized_value ?? $v->raw_value ?? '')))
    ->filter()
    ->countBy()
    ->sortDesc()
    ->take(30);

echo "Top OS values in catalog:\n";
foreach ($values as $val => $count) {
    echo "  {$count}x {$val}\n";
}

$displayDefIds = AttributeDefinition::query()
    ->whereIn('name', [
        'Dijagonala ekrana',
        'Dijagonala TV-a (inch)',
        'Veličina (inch)',
        'Dijagonala ekrana dodatno',
    ])
    ->pluck('id', 'name');

echo "\nDisplay definition IDs:\n";
foreach ($displayDefIds as $name => $id) {
    echo "  {$name}: {$id}\n";
}
