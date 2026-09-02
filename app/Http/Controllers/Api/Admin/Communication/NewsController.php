<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\IndexNewsRequest;
use App\Http\Requests\Communication\StoreNewsRequest;
use App\Http\Requests\Communication\UpdateNewsRequest;
use App\Http\Resources\Communication\NewsResource;
use App\Models\Communication\News;
use App\Services\Communication\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    private const RELATIONS = [
        'images',
        'videos',
        'creator:id,email',
        'updater:id,email',
    ];

    public function __construct(
        private readonly NewsService $newsService
    ) {}

    public function index(
        IndexNewsRequest $request
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $query = News::query()
            ->with(self::RELATIONS)
            ->search($validated['search'] ?? null);

        if (! empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        $news = $query
            ->orderByDesc('created_at')
            ->paginate(
                $validated['per_page'] ?? 15
            )
            ->withQueryString();

        return NewsResource::collection($news);
    }

    public function store(
        StoreNewsRequest $request
    ): NewsResource {
        $news = $this->newsService->create(
            $request->user('api'),
            $request->validated()
        );

        return new NewsResource($news);
    }

    public function show(News $news): NewsResource
    {
        $news->load(self::RELATIONS);

        return new NewsResource($news);
    }

    public function update(UpdateNewsRequest $request, News $news): NewsResource
    {
        $news = $this->newsService->update(
            $request->user('api'),
            $news,
            $request->validated()
        );

        return new NewsResource($news);
    }

    public function destroy(
        Request $request,
        News $news
    ): JsonResponse {
        $this->newsService->delete(
            $request->user('api'),
            $news
        );

        return response()->json([
            'success' => true,
            'message' => 'Noticia eliminada correctamente.',
        ]);
    }
}
