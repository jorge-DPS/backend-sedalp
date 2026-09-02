<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Auth\AccessStatus;
use App\Enums\Auth\UserStatus;
use App\Models\Audit\AccessStateChange;
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
        'account_status',
    ];

    protected $attributes = [
        'account_status' => UserStatus::ACTIVE->value,
        'token_version' => 1,
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
            'account_status' => UserStatus::class,
            'token_version' => 'integer',
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
        return $this->effectiveAccessStatus()
            === AccessStatus::ACTIVE;
    }

    public function effectiveAccessStatus(): AccessStatus
    {
        if ($this->trashed()) {
            return AccessStatus::DELETED;
        }

        if ($this->account_status === UserStatus::SUSPENDED) {
            return AccessStatus::SUSPENDED;
        }

        if ($this->staff_member_id === null) {
            return AccessStatus::ACTIVE;
        }

        $staffMember = $this->relationLoaded('staffMember')
            ? $this->getRelation('staffMember')
            : $this->staffMember()->withTrashed()->first();

        if (
            $staffMember === null
            || $staffMember->trashed()
            || ! $staffMember->active
        ) {
            return AccessStatus::DISABLED_BY_STAFF;
        }

        return AccessStatus::ACTIVE;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'ver' => $this->token_version,
        ];
    }

    public function accessStateChanges(): HasMany
    {
        return $this->hasMany(
            AccessStateChange::class,
            'target_user_id'
        );
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
