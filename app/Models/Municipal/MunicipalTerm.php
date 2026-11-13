<?php

namespace App\Models\Municipal;

use App\Models\Territory\Municipality;
use Database\Factories\Municipal\MunicipalTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MunicipalTerm extends Model
{
    /** @use HasFactory<MunicipalTermFactory> */
    use HasFactory;

    protected $fillable = ['municipality_id', 'start_year', 'end_year'];

    protected function casts(): array
    {
        return ['start_year' => 'integer', 'end_year' => 'integer'];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class)->withTrashed();
    }

    public function municipalAuthorities(): HasMany
    {
        return $this->hasMany(MunicipalAuthority::class);
    }
}
