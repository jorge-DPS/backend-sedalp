<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\IndexNewsTrashRequest;
use App\Http\Resources\Communication\NewsResource;
use App\Models\Communication\News;
use App\Services\Communication\NewsTrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsTrashController extends Controller
{
    private const RELATIONS = [
        'images',
        'videos',
        'creator:id,email',
        'updater:id,email',
    ];

    public function __construct(
        private readonly NewsTrashService $newsTrashService
    ) {}

    public function index(
        IndexNewsTrashRequest $request
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $news = News::onlyTrashed()
            ->with(self::RELATIONS)
            ->search($validated['search'] ?? null)
            ->orderByDesc('deleted_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return NewsResource::collection($news);
    }

    public function restore(
        Request $request,
        int $news
    ): NewsResource {
        $trashedNews = News::onlyTrashed()
            ->findOrFail($news);

        $restoredNews = $this->newsTrashService->restore(
            $trashedNews,
            $request->user('api')->id
        );

        return new NewsResource($restoredNews);
    }

    public function forceDelete(
        Request $request,
        int $news
    ): JsonResponse {
        $trashedNews = News::onlyTrashed()
            ->with('images:id,news_id,filename')
            ->findOrFail($news);

        $failedFiles = $this->newsTrashService->forceDelete(
            $trashedNews,
            $request->user('api')->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Noticia eliminada definitivamente.',
            'media_cleanup_pending' => $failedFiles !== [],
        ]);
    }
}
