<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Usager extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, AuditableSoftDeletes;

    protected $table = 'usagers';

    protected $fillable = [
        'numero',
        'nom',
        'prenom',
        'email',
        'mot_de_passe',
        'contact',
        'deleted_by',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'hashed',
        ];
    }

    /**
     * Get the password for the usager (mapping to mot_de_passe).
     */
    public function getAuthPassword(): string
    {
        return $this->mot_de_passe ?? '';
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'type' => 'usager',
            'numero' => $this->numero,
        ];
    }

    public function usagerSites(): HasMany
    {
        return $this->hasMany(UsagerSite::class, 'usager_id');
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'usager_sites', 'usager_id', 'site_id')
            ->withPivot(['numero', 'date_inscription', 'type', 'categorie_usager_id', 'statut'])
            ->withTimestamps();
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class, 'usager_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'usager_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'usager_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'usager_id');
    }
}
