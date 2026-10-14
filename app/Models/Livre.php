<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livre extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'livres';

    protected $fillable = [
        'site_id',
        'code',
        'titre',
        'auteur',
        'editeur',
        'annee_publication',
        'isbn',
        'categorie_livre_id',
        'image_principale',
        'fichier_numerique',
        'visible_en_ligne',
        'statut',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'annee_publication' => 'integer',
            'visible_en_ligne' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function categorieLivre(): BelongsTo
    {
        return $this->belongsTo(CategorieLivre::class, 'categorie_livre_id');
    }

    public function exemplaires(): HasMany
    {
        return $this->hasMany(Exemplaire::class, 'livre_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'livre_id');
    }
}
