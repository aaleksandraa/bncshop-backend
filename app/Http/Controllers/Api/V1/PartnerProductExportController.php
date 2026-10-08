<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPartnerProductExportQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartnerProductExportRequest;
use App\Http\Resources\ProductPartnerExportResource;
use App\Http\Resources\ProductPartnerFullExportResource;
use App\Models\PartnerApiClient;
use App\Services\Catalog\ProductPartnerExportService;
use App\Services\Integrations\PartnerExportSecurityService;
use Illuminate\Http\JsonResponse;

class PartnerProductExportController extends Controller
{
    use RespondsWithJson;
    use ResolvesPartnerProductExportQuery;

    public function __construct(
        private readonly ProductPartnerExportService $exportService,
        private readonly PartnerExportSecurityService $security,
    ) {}

    public function index(PartnerProductExportRequest $request): JsonResponse
    {
        /** @var PartnerApiClient $client */
        $client = $request->attributes->get('partner_api_client');

        if ($client->isUsedCatalog()) {
            return $this->security->error('Ovaj API ključ nije ovlašten za katalog novih proizvoda.', 403);
        }

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
}
