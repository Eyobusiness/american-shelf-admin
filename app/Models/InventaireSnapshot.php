<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventaire_snapshots';

    protected $fillable = [
        'site_id',
        'date_snapshot',
        'annee',
        'mois',
        'total_livres',
        'total_exemplaires',
        'total_disponibles',
        'total_empruntes',
        'total_consultation',
        'total_reserves',
        'total_indisponibles',
        'total_perdus',
        'repartition_etats',
        'repartition_statuts',
        'repartition_categories',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date_snapshot' => 'date',
            'annee' => 'integer',
            'mois' => 'integer',
            'total_livres' => 'integer',
            'total_exemplaires' => 'integer',
            'total_disponibles' => 'integer',
            'total_empruntes' => 'integer',
            'total_consultation' => 'integer',
            'total_reserves' => 'integer',
            'total_indisponibles' => 'integer',
            'total_perdus' => 'integer',
            'repartition_etats' => 'array',
            'repartition_statuts' => 'array',
            'repartition_categories' => 'array',
            'metadata' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
