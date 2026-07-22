<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Services\B2b\B2bPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bNewProductsDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesB2bMailIdentity;

    public string $customerName;

    public string $catalogUrl;

    /** @var array<int, string> */
    public array $productLines;

    public function __construct(
        Collection $products,
        public B2bCustomer $customer,
        public ?string $customIntro = null,
    ) {
        $this->customerName = (string) ($customer->user?->name ?? 'kupac');
        $this->catalogUrl = rtrim((string) config('bnc.frontend_url'), '/').'/b2b/katalog';

        $pricingService = app(B2bPricingService::class);
        $frontendUrl = rtrim((string) config('bnc.frontend_url'), '/');

        $this->productLines = $products
            ->map(function (B2bProduct $product) use ($pricingService, $frontendUrl): string {
                $pricing = $pricingService->calculate($product, $this->customer);
                $price = number_format($pricing['final_price'], 2, ',', '.');
                $productUrl = $frontendUrl.'/b2b/katalog/'.$product->slug;

                $line = $product->name;

                if (filled($product->sku)) {
                    $line .= ' ('.$product->sku.')';
                }

                $line .= ' — '.$price.' KM';
                $line .= ' — '.$productUrl;

                return $line;
            })
            ->values()
            ->all();
    }

    public function envelope(): Envelope
    {
        $count = count($this->productLines);
        $subject = $count === 1
            ? 'Novi proizvod u B2B katalogu'
            : 'Novi proizvodi u B2B katalogu ('.$count.')';

        return $this->b2bEnvelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.b2b.new-products-digest-text',
        );
    }
}
