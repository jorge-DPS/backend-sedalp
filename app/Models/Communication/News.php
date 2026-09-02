<?php

namespace App\Models\Communication;

use App\Enums\Communication\NewsStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'excerpt',
        'description',
        'content',
        'published_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'date',
            'status' => NewsStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        )->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        )->withTrashed();
    }

    public function images(): HasMany
    {
        return $this->hasMany(NewsImage::class)
            ->orderBy('position');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(NewsVideo::class)
            ->orderBy('position');
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('subtitle', 'ilike', "%{$search}%")
                ->orWhere('excerpt', 'ilike', "%{$search}%");
        });
    }
}
