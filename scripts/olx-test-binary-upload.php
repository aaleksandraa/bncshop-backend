<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Olx\OlxApiClient;
use Illuminate\Support\Facades\Http;

$listingId = (int) ($argv[1] ?? 77890238);
$url = $argv[2] ?? 'https://a1team.ba/storage/images/dd272631-cbad-4426-bf90-b3306c5905a3.webp';

$bytes = Http::timeout(30)->get($url)->body();
$tmpWebp = tempnam(sys_get_temp_dir(), 'olx').'.webp';
file_put_contents($tmpWebp, $bytes);

$jpegPath = tempnam(sys_get_temp_dir(), 'olx').'.jpg';
if (function_exists('imagecreatefromwebp')) {
    $img = @imagecreatefromwebp($tmpWebp);
    if ($img !== false) {
        imagejpeg($img, $jpegPath, 90);
        imagedestroy($img);
        echo "Converted webp to jpeg: ".filesize($jpegPath)." bytes\n";
    }
}

$client = app(OlxApiClient::class);
$token = $client->authenticate();
$baseUrl = rtrim(config('bnc.olx_api_base_url', 'https://api.olx.ba'), '/');

foreach ([
    ['path' => $jpegPath, 'name' => 'product.jpg', 'type' => 'image/jpeg'],
    ['path' => $tmpWebp, 'name' => 'product.webp', 'type' => 'image/webp'],
] as $file) {
    if (! is_file($file['path']) || filesize($file['path']) === 0) {
        continue;
    }

    echo "Trying multipart {$file['name']}...\n";

    $response = Http::baseUrl($baseUrl)
        ->timeout(60)
        ->withToken($token)
        ->attach('images[]', file_get_contents($file['path']), $file['name'], ['Content-Type' => $file['type']])
        ->post("/listings/{$listingId}/image-upload");

    echo "Status: {$response->status()}\n";
    echo substr($response->body(), 0, 500)."\n\n";
}

@unlink($tmpWebp);
@unlink($jpegPath);
