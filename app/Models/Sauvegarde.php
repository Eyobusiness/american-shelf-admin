<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sauvegarde extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'sauvegardes';

    public const UPDATED_AT = null;

    protected $fillable = [
        'site_id',
        'nom_fichier',
        'chemin_fichier',
        'taille',
        'date_sauvegarde',
        'statut',
        'created_at',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'taille' => 'integer',
            'date_sauvegarde' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
