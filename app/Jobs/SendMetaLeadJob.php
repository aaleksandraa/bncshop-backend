<?php

namespace App\Jobs;

use App\Models\InstallmentInquiry;
use App\Services\Integrations\MetaConversionsApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $inquiryId)
    {
        $this->onQueue('analytics');
    }

    public function handle(MetaConversionsApi $api): void
    {
        $inquiry = InstallmentInquiry::query()->with('product')->find($this->inquiryId);

        if ($inquiry === null) {
            return;
        }

        $api->sendLead($inquiry);
    }
}
