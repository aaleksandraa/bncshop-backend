<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartnerProductExportRequest;
use App\Http\Resources\ProductPartnerExportResource;
use App\Http\Resources\ProductPartnerFullExportResource;
use App\Models\PartnerApiClient;
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
        /** @var PartnerApiClient $client */
        $client = $request->attributes->get('partner_api_client');

        $perPage = min($this->resolvePerPage($request), 200);
        $page = max($this->resolvePage($request), 1);
        $updatedSince = $this->resolveUpdatedSince($request);

        $paginator = $this->exportService->paginate($updatedSince, $perPage, $page, $client);

        $resourceClass = $client->isFullExport()
            ? ProductPartnerFullExportResource::class
            : ProductPartnerExportResource::class;

        $items = $resourceClass::collection($paginator->items())->resolve();

        return $this->success($items, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => $this->buildFiltersMeta($request, $updatedSince),
        ]);
    }

    private function resolvePage(PartnerProductExportRequest $request): int
    {
        if ($request->filled('Page')) {
            return (int) $request->integer('Page');
        }

        return (int) $request->integer('page', 1);
    }

    private function resolvePerPage(PartnerProductExportRequest $request): int
    {
        if ($request->filled('PageSize')) {
            return (int) $request->integer('PageSize');
        }

        return (int) $request->integer('per_page', 100);
    }

    private function resolveUpdatedSince(PartnerProductExportRequest $request): ?Carbon
    {
        $raw = $request->input('ModifiedAfter') ?? $request->input('updated_since');

        if (! filled($raw)) {
            return null;
        }

        return Carbon::parse((string) $raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFiltersMeta(PartnerProductExportRequest $request, ?Carbon $updatedSince): array
    {
        $filters = [];

        if ($request->filled('ModifiedAfter')) {
            $filters['ModifiedAfter'] = Carbon::parse((string) $request->input('ModifiedAfter'))->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if ($request->filled('updated_since')) {
            $filters['updated_since'] = Carbon::parse((string) $request->input('updated_since'))->toIso8601String();
        }

        if ($updatedSince !== null && ! array_key_exists('ModifiedAfter', $filters) && ! array_key_exists('updated_since', $filters)) {
            $filters['updated_since'] = $updatedSince->toIso8601String();
        }

        return $filters;
    }
}
