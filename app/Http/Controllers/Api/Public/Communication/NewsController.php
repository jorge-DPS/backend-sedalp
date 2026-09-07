<?php

namespace App\Http\Controllers\Api\Public\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\Public\IndexNewsRequest;
use App\Http\Resources\Communication\Public\NewsDetailResource;
use App\Http\Resources\Communication\Public\NewsListResource;
use App\Services\Communication\Public\NewsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService
    ) {}

    public function index(
        IndexNewsRequest $request
    ): AnonymousResourceCollection {
        $news = $this->newsService->paginate(
            $request->validated()
        );

        return NewsListResource::collection($news);
    }

    public function show(string $slug): NewsDetailResource
    {
        $news = $this->newsService->findBySlug($slug);

        return new NewsDetailResource($news);
    }
}
