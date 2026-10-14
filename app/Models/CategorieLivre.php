<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieLivre extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'categories_livres';

    protected $fillable = [
        'site_id',
        'code',
        'nom',
        'description',
        'statut',
        'deleted_by',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function livres(): HasMany
    {
        return $this->hasMany(Livre::class, 'categorie_livre_id');
    }
}
