<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nombre', 'apellido', 'email', 'password', 'rol', 'telefono', 'direccion'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return $this->nombre.' '.$this->apellido;
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function ordenesComoEmpleado(): HasMany
    {
        return $this->hasMany(Orden::class, 'user_id');
    }

    public function ordenesComoCliente(): HasMany
    {
        return $this->hasMany(Orden::class, 'cliente_id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraInventario::class);
    }

    /**
     * Verificar si el usuario es admin
     */
    public function isAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Verificar si el usuario es empleado
     */
    public function isEmpleado(): bool
    {
        return $this->rol === 'empleado';
    }

    /**
     * Verificar si el usuario puede acceder al dashboard
     */
    public function canAccessDashboard(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Verificar si el usuario puede acceder al POS móvil
     */
    public function canAccessPos(): bool
    {
        return $this->isAdmin() || $this->isEmpleado();
    }
}
