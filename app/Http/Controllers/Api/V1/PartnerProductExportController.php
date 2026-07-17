<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartnerProductExportRequest;
use App\Http\Resources\ProductPartnerExportResource;
use App\Services\Catalog\ProductPartnerExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PartnerProductExportController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductPartnerExportService $exportService,
    ) {}

    public function index(PartnerProductExportRequest $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 100), 200);
        $page = max((int) $request->integer('page', 1), 1);
        $updatedSince = $request->filled('updated_since')
            ? Carbon::parse((string) $request->input('updated_since'))
            : null;

        $paginator = $this->exportService->paginate($updatedSince, $perPage, $page);

        $items = ProductPartnerExportResource::collection($paginator->items())->resolve();

        return $this->success($items, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'updated_since' => $updatedSince?->toIso8601String(),
            ],
        ]);
    }
}
