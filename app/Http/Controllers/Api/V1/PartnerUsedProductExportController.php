<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPartnerProductExportQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartnerProductExportRequest;
use App\Http\Resources\ProductPartnerUsedExportResource;
use App\Http\Resources\ProductPartnerUsedFullExportResource;
use App\Http\Resources\ProductPartnerUsedRemovalResource;
use App\Models\PartnerApiClient;
use App\Services\Catalog\ProductPartnerUsedExportService;
use App\Services\Integrations\PartnerExportSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PartnerUsedProductExportController extends Controller
{
    use RespondsWithJson;
    use ResolvesPartnerProductExportQuery;

    public function __construct(
        private readonly ProductPartnerUsedExportService $exportService,
        private readonly PartnerExportSecurityService $security,
    ) {}

    public function index(PartnerProductExportRequest $request): JsonResponse
    {
        $denied = $this->denyUnlessUsedCatalogClient($request);

        if ($denied !== null) {
            return $denied;
        }

        /** @var PartnerApiClient $client */
        $client = $request->attributes->get('partner_api_client');

        $perPage = min($this->resolvePerPage($request), 200);
        $page = max($this->resolvePage($request), 1);
        $updatedSince = $this->resolveUpdatedSince($request);

        $paginator = $this->exportService->paginate($updatedSince, $perPage, $page, $client);

        $resourceClass = $client->isFullExport()
            ? ProductPartnerUsedFullExportResource::class
            : ProductPartnerUsedExportResource::class;

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

    public function removals(PartnerProductExportRequest $request): JsonResponse
    {
        $denied = $this->denyUnlessUsedCatalogClient($request);

        if ($denied !== null) {
            return $denied;
        }

        $updatedSince = $this->resolveUpdatedSince($request);

        if ($updatedSince === null) {
            return $this->security->error('ModifiedAfter (ili updated_since) je obavezan za feed uklanjanja.', 422);
        }

        $perPage = min($this->resolvePerPage($request), 200);
        $page = max($this->resolvePage($request), 1);

        $paginator = $this->exportService->paginateRemovals($updatedSince, $perPage, $page);

        $items = ProductPartnerUsedRemovalResource::collection($paginator->items())->resolve();

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

    private function denyUnlessUsedCatalogClient(PartnerProductExportRequest $request): ?JsonResponse
    {
        /** @var PartnerApiClient|null $client */
        $client = $request->attributes->get('partner_api_client');

        if ($client === null || ! $client->isUsedCatalog()) {
            return $this->security->error('Ovaj API ključ nije ovlašten za polovni katalog.', 403);
        }

        return null;
    }
}
