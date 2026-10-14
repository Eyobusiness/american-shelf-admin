<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Utilisateur extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, AuditableSoftDeletes;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'site_id',
        'nom',
        'prenom',
        'telephone',
        'email',
        'mot_de_passe',
        'role_id',
        'statut',
        'derniere_connexion',
        'deleted_by',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'derniere_connexion' => 'datetime',
            'mot_de_passe' => 'hashed',
        ];
    }

    /**
     * Get the password for the user (mapping to mot_de_passe).
     */
    public function getAuthPassword(): string
    {
        return $this->mot_de_passe;
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
            'site_id' => $this->site_id,
            'role_id' => $this->role_id,
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function journauxActivites(): HasMany
    {
        return $this->hasMany(JournalActivite::class, 'utilisateur_id');
    }
}
