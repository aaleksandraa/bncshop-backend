<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Jobs\RunApiSyncJob;
use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\IntegrationApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSyncController extends Controller
{
    use RespondsWithJson;

    public function status(IncrementalSyncScheduler $scheduler): JsonResponse
    {
        $sources = ApiSource::query()
            ->with(['importJobs' => fn ($query) => $query->latest()->limit(1)])
            ->get()
            ->map(fn (ApiSource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'target_system_code' => $source->target_system_code,
                'connection_status' => $source->connection_status,
                'sync_interval_minutes' => $source->sync_interval_minutes,
                'auto_sync_enabled' => (bool) $source->auto_sync_enabled,
                'is_due' => $scheduler->isDue($source),
                'last_successful_sync_at' => $source->last_successful_sync_at,
                'next_sync_at' => $source->nextSyncAt(),
                'last_error' => $source->last_error,
                'latest_job' => $source->importJobs->first(),
            ]);

        return $this->success($sources);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:api_sources,id'],
            'type' => ['nullable', 'string', 'in:full,incremental'],
        ]);

        $source = ApiSource::query()->findOrFail($validated['source_id']);

        if (! $source->usesIntegrationApiImport()) {
            return $this->error('Ovaj API izvor ne koristi IntegrationApiClient import sync.', 422);
        }

        $fullSync = ($validated['type'] ?? 'incremental') === 'full';

        RunApiSyncJob::dispatch($source, $fullSync, skipMetadata: ! $fullSync);

        return $this->success([
            'message' => 'Sync job dispatched',
            'source_id' => $source->id,
            'type' => $fullSync ? 'full' : 'incremental',
        ], status: 202);
    }

    public function jobs(Request $request): JsonResponse
    {
        $jobs = ApiImportJob::query()
            ->with('apiSource:id,name')
            ->when($request->integer('source_id'), fn ($query, $sourceId) => $query->where('api_source_id', $sourceId))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return $this->paginated($jobs, $jobs->items());
    }

    public function showJob(int $id): JsonResponse
    {
        $job = ApiImportJob::query()
            ->with(['apiSource:id,name', 'items'])
            ->findOrFail($id);

        return $this->success($job);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:api_sources,id'],
        ]);

        $source = ApiSource::query()->findOrFail($validated['source_id']);

        if (! $source->usesIntegrationApiImport()) {
            return $this->error('Provjera konekcije nije podržana za ovaj tip API izvora.', 422);
        }

        try {
            IntegrationApiClient::forSource($source)->ensureAuthenticated();
            $source->update([
                'connection_status' => 'connected',
                'last_error' => null,
            ]);

            return $this->success([
                'ok' => true,
                'connection_status' => 'connected',
            ]);
        } catch (\Throwable $e) {
            report($e);

            $source->update([
                'connection_status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            return $this->error('Provjera konekcije nije uspjela. Detalji su zabilježeni u logu.', 502);
        }
    }

    public function updateSource(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'auto_sync_enabled' => ['sometimes', 'boolean'],
            'sync_interval_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $source = ApiSource::query()->findOrFail($id);

        if ($source->target_system_code === 'eline' && array_key_exists('auto_sync_enabled', $validated)) {
            unset($validated['auto_sync_enabled']);
        }

        $source->update($validated);

        return $this->success([
            'id' => $source->id,
            'auto_sync_enabled' => (bool) $source->auto_sync_enabled,
            'sync_interval_minutes' => $source->sync_interval_minutes,
        ]);
    }
}
