<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait RespondsWithJson
{
    protected function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
            'errors' => [],
        ], $status);
    }

    /**
     * @param  string|array<int, string>  $errors
     */
    protected function error(string|array $errors, int $status = 422): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => [],
            'errors' => is_array($errors) ? $errors : [$errors],
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, mixed $data = null): JsonResponse
    {
        return $this->success($data ?? $paginator->items(), [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
