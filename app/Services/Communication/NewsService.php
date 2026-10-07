<?php

namespace App\Services\Communication;

use App\Enums\Communication\NewsStatus;
use App\Models\Communication\News;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class NewsService
{
    private const RELATIONS = [
        'images',
        'videos',
        'creator:id,email',
        'updater:id,email',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): News
    {
        $status = $attributes['status'] ?? NewsStatus::DRAFT->value;

        if ($status === NewsStatus::PUBLISHED->value) {
            $this->authorizePublication($actor);
        }

        return DB::transaction(function () use (
            $actor,
            $attributes,
            $status
        ): News {
            $news = new News;
            $news->fill($attributes);
            $news->slug = $this->generateUniqueSlug();
            $news->status = $status;
            $news->created_by = $actor->id;
            $news->save();

            return $news->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        User $actor,
        News $news,
        array $attributes
    ): News {
        return DB::transaction(function () use (
            $actor,
            $news,
            $attributes
        ): News {
            $lockedNews = News::query()
                ->whereKey($news->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->publicationChanged($lockedNews, $attributes)) {
                $this->authorizePublication($actor);
            }

            $lockedNews->fill($attributes);
            $lockedNews->updated_by = $actor->id;
            $lockedNews->save();

            return $lockedNews->load(self::RELATIONS);
        });
    }

    public function delete(User $actor, News $news): void
    {
        DB::transaction(function () use ($actor, $news): void {
            $lockedNews = News::query()
                ->whereKey($news->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedNews->updated_by = $actor->id;
            $lockedNews->save();
            $lockedNews->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicationChanged(
        News $news,
        array $attributes
    ): bool {
        $currentStatus = $news->status;
        $newStatus = NewsStatus::from(
            $attributes['status'] ?? $currentStatus->value
        );

        $statusChanged = $currentStatus !== $newStatus
            && (
                $currentStatus === NewsStatus::PUBLISHED
                || $newStatus === NewsStatus::PUBLISHED
            );

        if ($statusChanged) {
            return true;
        }

        if (
            ! array_key_exists('published_at', $attributes)
            || $currentStatus !== NewsStatus::PUBLISHED
        ) {
            return false;
        }

        $newPublishedAt = filled($attributes['published_at'])
            ? CarbonImmutable::parse(
                (string) $attributes['published_at']
            )->toDateString()
            : null;

        return $news->published_at?->toDateString()
            !== $newPublishedAt;
    }

    private function authorizePublication(User $actor): void
    {
        abort_unless(
            $actor->can('news.publish'),
            403,
            'No tiene permiso para cambiar el estado de publicación.'
        );
    }

    protected function generateSlugCandidate(): string
    {
        return (string) random_int(100_000_000_000, 999_999_999_999);
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = $this->generateSlugCandidate();

            DB::selectOne(
                <<<'SQL'
                    SELECT pg_advisory_xact_lock(
                        hashtext('news_slug'),
                        hashtext(?)
                    )
                    SQL,
                [$slug]
            );
        } while (
            News::withTrashed()
                ->where('slug', $slug)
                ->exists()
        );

        return $slug;
    }
}
