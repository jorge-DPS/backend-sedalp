<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitePage extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name'];

    public function sections(): HasMany
    {
        return $this->hasMany(SiteSection::class)->orderBy('position')->orderBy('id');
    }
}
