<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSellerInstallmentInquiryStatusRequest;
use App\Models\InstallmentInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerInstallmentInquiryController extends Controller
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        $query = InstallmentInquiry::query()->with('cancelledBy')->orderByDesc('created_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($type = $request->string('installment_type')->toString()) {
            $query->where('installment_type', $type);
        }

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('cancellation_reason', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->paginate(min((int) $request->integer('per_page', 20), 50));

        $items = collect($inquiries->items())
            ->map(fn (InstallmentInquiry $inquiry) => $this->formatInquiry($inquiry))
            ->all();

        return $this->paginated($inquiries, $items);
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = InstallmentInquiry::query()->with('cancelledBy')->findOrFail($id);

        return $this->success($this->formatInquiry($inquiry));
    }

    public function updateStatus(UpdateSellerInstallmentInquiryStatusRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user === null || (! $user->can('manage_orders') && ! $user->can('installment_inquiries.update'))) {
            return $this->error('Nemate dozvolu za promjenu statusa upita.', 403);
        }

        $inquiry = InstallmentInquiry::query()->findOrFail($id);
        $inquiry->applyStatus(
            $request->validated('status'),
            $request->validated('cancellation_reason'),
            $user,
        );
        $inquiry->load('cancelledBy');

        return $this->success($this->formatInquiry($inquiry));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInquiry(InstallmentInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'status' => $inquiry->status,
            'status_label' => InstallmentInquiry::statusOptions()[$inquiry->status] ?? $inquiry->status,
            'cancellation_reason' => $inquiry->cancellation_reason,
            'cancelled_at' => $inquiry->cancelled_at,
            'cancelled_by_name' => $inquiry->cancelledBy?->name,
            'created_at' => $inquiry->created_at,
            'first_name' => $inquiry->first_name,
            'last_name' => $inquiry->last_name,
            'customer_name' => $inquiry->full_name,
            'phone' => $inquiry->phone,
            'email' => $inquiry->email,
            'product_id' => $inquiry->product_id,
            'product_name' => $inquiry->product_name,
            'product_slug' => $inquiry->product_slug,
            'quantity' => $inquiry->quantity,
            'base_price' => $inquiry->base_price,
            'installment_type' => $inquiry->installment_type,
            'installment_type_label' => InstallmentInquiry::typeOptions()[$inquiry->installment_type] ?? $inquiry->installment_type,
            'months' => $inquiry->months,
            'monthly_amount' => $inquiry->monthly_amount,
            'total_amount' => $inquiry->total_amount,
            'interest_percent' => round((float) $inquiry->interest_rate * 100, 2),
            'provision_percent' => round((float) $inquiry->provision_rate * 100, 2),
        ];
    }
}
