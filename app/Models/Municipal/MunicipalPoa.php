<?php

namespace App\Models\Municipal;

use App\Models\Territory\Municipality;
use Database\Factories\Municipal\MunicipalPoaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MunicipalPoa extends Model
{
    /** @use HasFactory<MunicipalPoaFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'fiscal_year',
        'title',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class)->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MunicipalPoaVersion::class);
    }

    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(MunicipalPoaDocument::class, MunicipalPoaVersion::class);
    }
}
