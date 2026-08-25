<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsImage extends Model
{
    use HasFactory;

    /**
     * El backend define dónde pertenecen
     * físicamente las imágenes de noticias.
     */
    public const MEDIA_DIRECTORY = 'communication/news';

    protected $fillable = [
        'news_id',
        'filename',
        'alt',
        'caption',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
