<?php

namespace App\Models\Territory;

use App\Enums\Territory\MembershipOriginType;
use Database\Factories\Territory\MunicipalityRegionMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalityRegionMembership extends Model
{
    /** @use HasFactory<MunicipalityRegionMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'region_id',
        'valid_from',
        'valid_to',
        'origin_type',
        'agreement_reference',
        'agreement_date',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'origin_type' => MembershipOriginType::class,
            'agreement_date' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class)->withTrashed();
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    /**
     * Incluye el inicio y excluye el fin del intervalo de pertenencia.
     *
     * @param  Builder<MunicipalityRegionMembership>  $query
     * @return Builder<MunicipalityRegionMembership>
     */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', $date);
            });
    }
}
