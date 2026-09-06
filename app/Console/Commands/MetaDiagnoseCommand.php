<?php

namespace App\Console\Commands;

use App\Services\Integrations\MetaCatalogFeedService;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MetaDiagnoseCommand extends Command
{
    protected $signature = 'meta:diagnose';

    protected $description = 'Check Meta Pixel/CAPI/catalog configuration';

    public function handle(
        TrackingSettings $trackingSettings,
        MetaCatalogFeedService $feedService,
    ): int {
        $settings = $trackingSettings->all();

        $datasetId = trim((string) ($settings['fb_dataset_id'] ?? ''));
        $pixelId = trim((string) ($settings['fb_pixel_id'] ?? ''));
        $token = trim((string) ($settings['fb_access_token'] ?? ''));
        $testCode = trim((string) ($settings['fb_test_event_code'] ?? ''));

        $this->info('Meta integration status');
        $this->line('Dataset ID: '.($datasetId !== '' ? $datasetId : '[MISSING]'));
        $this->line('Pixel ID: '.($pixelId !== '' ? $pixelId : '[MISSING — using dataset fallback in API]'));
        $this->line('CAPI token: '.($token !== '' ? 'configured' : '[MISSING]'));
        $this->line('Test event code: '.($testCode !== '' ? $testCode.' (events go to Test Events tab only!)' : 'not set'));
        $this->line('Catalog feed URL: '.$feedService->feedUrl());
        $this->line('Frontend URL: '.config('bnc.frontend_url'));
        $this->newLine();

        if ($token === '' || ($datasetId === '' && $pixelId === '')) {
            $this->error('CAPI is NOT configured. Fill Dataset ID + access token in admin.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post(
                    'https://graph.facebook.com/v26.0/'.($datasetId !== '' ? $datasetId : $pixelId).'/events?access_token='.$token,
                    [
                        'data' => [[
                            'event_name' => 'PageView',
                            'event_time' => time(),
                            'event_id' => 'diagnose-'.time(),
                            'action_source' => 'website',
                            'event_source_url' => rtrim((string) config('bnc.frontend_url', 'https://bnc.ba'), '/'),
                            'user_data' => [
                                'client_ip_address' => '127.0.0.1',
                                'client_user_agent' => 'BNC-Meta-Diagnose/1.0 (server-side connectivity test)',
                            ],
                        ]],
                    ],
                );

            if (! $response->successful()) {
                $body = $response->json();
                $metaMessage = is_array($body)
                    ? ($body['error']['error_user_msg'] ?? $body['error']['message'] ?? null)
                    : null;

                $this->error('CAPI test failed: HTTP '.$response->status());
                if (is_string($metaMessage) && $metaMessage !== '') {
                    $this->line($metaMessage);
                } else {
                    $this->line($response->body());
                }

                return self::FAILURE;
            }

            $this->info('CAPI test PageView sent successfully.');
        } catch (\Throwable $exception) {
            $this->error('CAPI test failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($pixelId === '' && $datasetId !== '') {
            $this->warn('Tip: save tracking settings in admin once — Pixel ID will copy from Dataset ID for clarity.');
        }

        return self::SUCCESS;
    }
}
