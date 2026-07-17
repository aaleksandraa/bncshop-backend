<?php

namespace App\Jobs;

use App\Mail\B2b\B2bNewProductsDigestMail;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\B2bSetting;
use App\Services\B2b\B2bNewProductNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendB2bNewProductDigestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'b2b-new-product-digest';
    }

    public function handle(B2bNewProductNotificationService $notificationService): void
    {
        if (! B2bSetting::instance()->notify_customers_on_new_product) {
            $notificationService->pullDigestProductIds();

            return;
        }

        $productIds = $notificationService->pullDigestProductIds();

        if ($productIds === []) {
            return;
        }

        /** @var Collection<int, B2bProduct> $products */
        $products = B2bProduct::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('id')
            ->get();

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

                    Mail::to($email)->queue(new B2bNewProductsDigestMail($products, $customer));
                }
            });
    }
}
