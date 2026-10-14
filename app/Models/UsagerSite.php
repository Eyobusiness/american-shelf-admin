<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsagerSite extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'usager_sites';

    protected $fillable = [
        'site_id',
        'usager_id',
        'numero',
        'date_inscription',
        'type',
        'categorie_usager_id',
        'statut',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_inscription' => 'date',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function usager(): BelongsTo
    {
        return $this->belongsTo(Usager::class, 'usager_id');
    }

    public function categorieUsager(): BelongsTo
    {
        return $this->belongsTo(CategorieUsager::class, 'categorie_usager_id');
    }
}
