<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Mail\Concerns\LogsMailableIdentity;
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
    use Queueable, SerializesModels, UsesB2bMailIdentity, LogsMailableIdentity;

    public string $customerName;

    public string $catalogUrl;

    /** @var array<int, array{name: string, sku: ?string, price: string, url: string}> */
    public array $products;

    public function __construct(
        Collection $products,
        public B2bCustomer $customer,
        public ?string $customIntro = null,
    ) {
        $this->customerName = (string) ($customer->user?->name ?? 'kupac');
        $this->catalogUrl = B2bProduct::catalogUrl();

        $pricingService = app(B2bPricingService::class);

        $this->products = $products
            ->map(function (B2bProduct $product) use ($pricingService): array {
                $pricing = $pricingService->calculate($product, $this->customer);

                return [
                    'name' => $product->name,
                    'sku' => filled($product->sku) ? $product->sku : null,
                    'price' => number_format($pricing['final_price'], 2, ',', '.'),
                    'url' => $product->frontendUrl(),
                ];
            })
            ->values()
            ->all();
    }

    public function envelope(): Envelope
    {
        $count = count($this->products);
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
