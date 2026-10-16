<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteMedia extends Model
{
    use HasFactory;

    protected $fillable = ['profile', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'];

    protected $table = 'site_media';

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function desktopSections(): HasMany
    {
        return $this->hasMany(SiteSection::class, 'desktop_media_id');
    }

    public function mobileSections(): HasMany
    {
        return $this->hasMany(SiteSection::class, 'mobile_media_id');
    }
}
