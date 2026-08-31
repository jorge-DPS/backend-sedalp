<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Communication\News;
use App\Models\People\StaffMember;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    // use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $fillable = [
        'staff_member_id',

        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function canAccessApi(): bool
    {
        /*
         * Las cuentas técnicas pueden existir
         * sin personal asociado.
         */
        if ($this->staff_member_id === null) {
            return true;
        }

        /*
         * Incluimos registros eliminados lógicamente
         * para poder bloquear también ese caso.
         */
        $staffMember = $this
            ->staffMember()
            ->withTrashed()
            ->first();

        if ($staffMember === null) {
            return false;
        }

        if ($staffMember->trashed()) {
            return false;
        }

        return $staffMember->active === true;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function createdNews(): HasMany
    {
        return $this->hasMany(
            News::class,
            'created_by'
        );
    }

    public function updatedNews(): HasMany
    {
        return $this->hasMany(
            News::class,
            'updated_by'
        );
    }
}
