<?php

namespace App\Services\Communication\Public;

use App\Models\Communication\News;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewsService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return News::query()
            ->publiclyVisible()
            ->with('coverImage')
            ->search($filters['search'] ?? null)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();
    }

    public function findBySlug(string $slug): News
    {
        return News::query()
            ->publiclyVisible()
            ->with([
                'images',
                'videos',
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
