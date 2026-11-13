<?php

namespace App\Models\Municipal;

use App\Enums\Municipal\MunicipalAuthorityRole;
use App\Models\Territory\Municipality;
use Database\Factories\Municipal\MunicipalAuthorityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalAuthority extends Model
{
    /** @use HasFactory<MunicipalAuthorityFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'municipal_term_id',
        'full_name',
        'role',
        'position_title',
        'photo_path',
        'term_start',
        'term_end',
        'is_current',
        'is_published',
        'source_reference',
        'notes',
    ];

    protected $attributes = ['is_current' => false, 'is_published' => false];

    protected $hidden = ['photo_path'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'municipal_term_id' => 'integer',
            'role' => MunicipalAuthorityRole::class,
            'term_start' => 'immutable_date',
            'term_end' => 'immutable_date',
            'is_current' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class)->withTrashed();
    }

    public function municipalTerm(): BelongsTo
    {
        return $this->belongsTo(MunicipalTerm::class);
    }

    /** @param Builder<MunicipalAuthority> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        foreach (['municipal_term_id', 'role', 'is_current', 'is_published'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['search'] ?? null)) {
            $query->whereLike('full_name', '%'.$filters['search'].'%');
        }

        return $query;
    }
}
