<?php

namespace App\Models\People;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StaffMember extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'first_name',
        'second_name',
        'paternal_surname',
        'maternal_surname',
        'ci',
        'ci_complement',
        'birth_date',
        'phone',
        'email',
        'address',
        'position_id',
        'profession_id',
        'entry_date',
        'resignation_date',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
        ];
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
