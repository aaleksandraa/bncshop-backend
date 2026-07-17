<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InstallmentInquiryRequest;
use App\Mail\InstallmentInquiryNotification;
use App\Models\InstallmentInquiry;
use App\Models\Product;
use App\Services\Commerce\InstallmentCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InstallmentInquiryController extends Controller
{
    public function __construct(
        private readonly InstallmentCalculator $calculator,
    ) {}

    public function store(InstallmentInquiryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $quantity = (int) ($validated['quantity'] ?? 1);
        $product = null;
        $basePrice = 0.0;

        if (! empty($validated['product_slug'])) {
            $product = Product::query()
                ->public()
                ->active()
                ->where('slug', $validated['product_slug'])
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product_slug' => ['Proizvod nije pronađen.'],
                ]);
            }

            $basePrice = round((float) $product->display_price * $quantity, 2);
        }

        if ($basePrice <= 0) {
            throw ValidationException::withMessages([
                'product_slug' => ['Za slanje upita potreban je proizvod.'],
            ]);
        }

        if (! $this->calculator->isEnabled()) {
            throw ValidationException::withMessages([
                'product_slug' => ['Kupovina na rate trenutno nije dostupna.'],
            ]);
        }

        if (! $this->calculator->isInstallmentEligible($basePrice)) {
            throw ValidationException::withMessages([
                'product_slug' => [
                    $this->calculator->eligibilityRangeMessage(),
                ],
            ]);
        }

        $plan = $this->calculator->calculatePlan(
            $validated['installment_type'],
            $basePrice,
            (int) $validated['months'],
        );

        if ($plan === null) {
            throw ValidationException::withMessages([
                'months' => ['Odabrani plan otplate nije dostupan za ovu cijenu.'],
            ]);
        }

        if ($validated['installment_type'] === 'shopping_card') {
            $expectedMonths = (int) $this->calculator->config()['card_months'];

            if ((int) $validated['months'] !== $expectedMonths) {
                throw ValidationException::withMessages([
                    'months' => ['Neispravan broj rata za shopping kartice.'],
                ]);
            }
        }

        $inquiry = InstallmentInquiry::query()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'product_id' => $product?->id,
            'product_name' => $product?->name,
            'product_slug' => $product?->slug,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'installment_type' => $plan['type'],
            'months' => $plan['months'],
            'monthly_amount' => $plan['monthly_amount'],
            'total_amount' => $plan['total_amount'],
            'interest_rate' => $plan['interest_rate'],
            'provision_rate' => $plan['provision_rate'],
            'calculation_snapshot' => $plan,
            'status' => 'nova',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $sellerEmail = config('bnc.seller_notification_email');

        if ($sellerEmail) {
            Mail::to($sellerEmail)->queue(new InstallmentInquiryNotification($inquiry));
        }

        return response()->json([
            'data' => [
                'id' => $inquiry->id,
                'message' => 'Primili smo vaš upit. Kontaktiraćemo vas uskoro.',
            ],
            'meta' => [],
            'errors' => [],
        ], 201);
    }
}
