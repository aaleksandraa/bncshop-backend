<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Requests\Api\V1\PartnerProductExportRequest;
use Illuminate\Support\Carbon;

trait ResolvesPartnerProductExportQuery
{
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
