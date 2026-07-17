<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Blog\BlogPostBlockResolver;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly BlogPostBlockResolver $blockResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max((int) $request->integer('per_page', 12), 1), 24);
        $cacheKey = "blog:list:{$page}:{$perPage}";

        $payload = $this->productReadCache->rememberBlogList($cacheKey, 600, function () use ($page, $perPage): array {
            $paginator = BlogPost::query()
                ->published()
                ->with('author:id,name')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => collect($paginator->items())
                    ->map(fn (BlogPost $post): array => $this->blockResolver->presentSummary($post))
                    ->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ];
        });

        return $this->success($payload['items'], [
            'pagination' => $payload['pagination'],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $payload = $this->productReadCache->rememberBlogPost($slug, 600, function () use ($slug): array {
            $post = BlogPost::query()
                ->published()
                ->with('author:id,name')
                ->where('slug', $slug)
                ->firstOrFail();

            return $this->blockResolver->present($post);
        });

        return $this->success($payload);
    }
}
