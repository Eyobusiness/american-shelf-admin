<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'sites';

    protected $fillable = [
        'code',
        'nom',
        'logo',
        'description',
        'adresse',
        'telephone',
        'email',
        'statut',
        'date_creation',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_creation' => 'date',
        ];
    }

    public function categoriesUsagers(): HasMany
    {
        return $this->hasMany(CategorieUsager::class, 'site_id');
    }

    public function categoriesLivres(): HasMany
    {
        return $this->hasMany(CategorieLivre::class, 'site_id');
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(Configuration::class, 'site_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'site_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'site_id');
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class, 'site_id');
    }

    public function acquisitions(): HasMany
    {
        return $this->hasMany(Acquisition::class, 'site_id');
    }

    public function livres(): HasMany
    {
        return $this->hasMany(Livre::class, 'site_id');
    }

    public function exemplaires(): HasMany
    {
        return $this->hasMany(Exemplaire::class, 'site_id');
    }

    public function usagerSites(): HasMany
    {
        return $this->hasMany(UsagerSite::class, 'site_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class, 'site_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'site_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'site_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'site_id');
    }

    public function journauxActivites(): HasMany
    {
        return $this->hasMany(JournalActivite::class, 'site_id');
    }

    public function sauvegardes(): HasMany
    {
        return $this->hasMany(Sauvegarde::class, 'site_id');
    }

    public function inventaireSnapshots(): HasMany
    {
        return $this->hasMany(InventaireSnapshot::class, 'site_id');
    }

    public function exemplaireMouvements(): HasMany
    {
        return $this->hasMany(ExemplaireMouvement::class, 'site_id');
    }
}
