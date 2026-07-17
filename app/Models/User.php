<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_customer' => 'boolean',
            'is_b2b_customer' => 'boolean',
        ];
    }

    public function b2bCustomer(): HasOne
    {
        return $this->hasOne(B2bCustomer::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Create a user and set privilege flags outside mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createAccount(array $attributes): self
    {
        $isCustomer = (bool) ($attributes['is_customer'] ?? false);
        $isB2bCustomer = (bool) ($attributes['is_b2b_customer'] ?? false);
        unset($attributes['is_customer'], $attributes['is_b2b_customer']);

        $user = static::query()->create($attributes);
        $user->forceFill([
            'is_customer' => $isCustomer,
            'is_b2b_customer' => $isB2bCustomer,
        ])->save();

        return $user->refresh();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_customer || $this->is_b2b_customer) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return $this->hasRole(['Super Admin', 'Admin']);
        }

        if ($panel->getId() === 'b2b-admin') {
            return $this->hasRole(['Super Admin', 'Admin', 'B2B Admin'])
                || $this->can('b2b_settings.view');
        }

        return false;
    }
}
