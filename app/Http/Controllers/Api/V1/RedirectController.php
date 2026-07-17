<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;

class RedirectController extends Controller
{
    use RespondsWithJson;

    public function __invoke(): JsonResponse
    {
        $redirects = Redirect::query()
            ->orderBy('from_path')
            ->get(['from_path', 'to_path', 'status_code']);

        return $this->success($redirects);
    }
}
