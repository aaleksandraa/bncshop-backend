<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cats = [163, 38, 39, 775, 2529, 166, 816, 170, 162, 1499, 248, 1748, 776, 2076, 2464];

foreach ($cats as $id) {
    echo "=== {$id} ===\n";
    $attrs = App\Models\OlxCategoryAttribute::query()
        ->where('olx_category_id', $id)
        ->orderByDesc('required')
        ->orderBy('olx_attribute_id')
        ->get(['olx_attribute_id', 'display_name', 'name', 'input_type', 'required', 'options_json']);

    foreach ($attrs as $a) {
        $opts = is_array($a->options_json) ? array_slice(array_values($a->options_json), 0, 5) : [];
        echo sprintf(
            "%s %s | %s | req=%s | opts=%s\n",
            $a->required ? '*' : ' ',
            $a->olx_attribute_id,
            $a->display_name ?: $a->name,
            $a->required ? 'Y' : 'N',
            json_encode($opts, JSON_UNESCAPED_UNICODE),
        );
    }
}
