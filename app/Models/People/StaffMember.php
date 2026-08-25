<?php

namespace App\Models\People;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;


class StaffMember extends Model
{
    //
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_names',
        'paternal_surname',
        'maternal_surname',
        'birth_date',
        'ci',
        'ci_complement',
        'phone',
        'email',
        'organizational_unit_id',
        'position_id',
        'profession_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
