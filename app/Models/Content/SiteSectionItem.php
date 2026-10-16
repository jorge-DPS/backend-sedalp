<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSectionItem extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'title', 'description', 'icon_key', 'position'];

    protected $attributes = ['position' => 0];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
