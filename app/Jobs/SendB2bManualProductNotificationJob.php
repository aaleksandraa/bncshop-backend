<?php

namespace App\Jobs;

use App\Mail\B2b\B2bNewProductsDigestMail;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Services\B2b\B2bManualProductNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendB2bManualProductNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(
        public array $productIds,
        public ?string $customIntro = null,
    ) {}

    public function handle(B2bManualProductNotificationService $notificationService): void
    {
        /** @var Collection<int, B2bProduct> $products */
        $products = $notificationService->resolveProducts($this->productIds);

        if ($products->isEmpty()) {
            return;
        }

        B2bCustomer::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_b2b_customer', true))
            ->with('user')
            ->chunkById(100, function ($customers) use ($products): void {
                foreach ($customers as $customer) {
                    $email = $customer->user?->email;

                    if (! filled($email)) {
                        continue;
                    }

                    Mail::to($email)->queue(new B2bNewProductsDigestMail(
                        $products,
                        $customer,
                        $this->customIntro,
                    ));
                }
            });
    }
}
