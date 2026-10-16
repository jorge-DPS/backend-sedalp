<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'position',
        'eyebrow',
        'title',
        'subtitle',
        'description',
        'desktop_media_id',
        'mobile_media_id',
        'image_alt',
        'options',
    ];

    protected $attributes = ['position' => 0, 'options' => '{}'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'options' => 'array'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SiteSectionItem::class)->orderBy('position')->orderBy('id');
    }

    public function desktopMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'desktop_media_id');
    }

    public function mobileMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'mobile_media_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
