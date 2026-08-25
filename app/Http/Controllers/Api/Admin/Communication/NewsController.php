<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Enums\NewsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\IndexNewsRequest;
use App\Http\Requests\Communication\StoreNewsRequest;
use App\Http\Requests\Communication\UpdateNewsRequest;
use App\Http\Resources\Communication\NewsResource;
use App\Models\Communication\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(IndexNewsRequest $request)
    {
        $validated = $request->validated();

        $query = News::query()
            ->with([
                'images',
                'videos',
                'creator:id,email',
                'updater:id,email',
            ])
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

    public function store(StoreNewsRequest $request): NewsResource {
        $validated = $request->validated();

        $status = $validated['status']
            ?? NewsStatus::DRAFT->value;

        if ($status === NewsStatus::PUBLISHED->value) {
            abort_unless(
                $request->user('api')->can('news.publish'),
                403,
                'No tiene permiso para publicar noticias.'
            );
        }

        $news = DB::transaction(function () use (
            $request,
            $validated,
            $status
        ) {
            $news = new News();

            $news->fill($validated);

            $news->slug = $this->generateUniqueSlug(
                $validated['title']
            );

            $news->status = $status;

            $news->created_by = $request
                ->user('api')
                ->id;

            $news->save();

            return $news;
        });

        $news->load([
            'images',
            'videos',
            'creator:id,email',
            'updater:id,email',
        ]);

        return new NewsResource($news);
    }

    public function show(News $news): NewsResource {
        $news->load([
            'images',
            'videos',
            'creator:id,email',
            'updater:id,email',
        ]);

        return new NewsResource($news);
    }

    public function update(UpdateNewsRequest $request, News $news): NewsResource {
        $validated = $request->validated();

        if (array_key_exists('status', $validated)) {
            $currentStatus = $news->status->value;
            $newStatus = $validated['status'];

            $publicationChanged =
                $currentStatus !== $newStatus
                && (
                    $currentStatus === NewsStatus::PUBLISHED->value
                    || $newStatus === NewsStatus::PUBLISHED->value
                );

            if ($publicationChanged) {
                abort_unless(
                    $request->user('api')->can('news.publish'),
                    403,
                    'No tiene permiso para cambiar el estado de publicación.'
                );
            }
        }

        DB::transaction(function () use (
            $request,
            $news,
            $validated
        ) {
            $news->fill($validated);

            /*
             * No regeneramos automáticamente el slug
             * cuando cambia el título.
             *
             * De esta manera no rompemos URLs públicas.
             */

            $news->updated_by = $request
                ->user('api')
                ->id;

            $news->save();
        });

        $news->load([
            'images',
            'videos',
            'creator:id,email',
            'updater:id,email',
        ]);

        return new NewsResource($news);
    }

    public function destroy(
        News $news
    ): JsonResponse {
        DB::transaction(function () use ($news) {
            $news->updated_by = auth('api')->id();
            $news->save();

            $news->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Noticia eliminada correctamente.',
        ]);
    }

    private function generateUniqueSlug(
        string $title
    ): string {
        $baseSlug = Str::slug($title);

        if (blank($baseSlug)) {
            $baseSlug = 'noticia';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            News::withTrashed()
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
