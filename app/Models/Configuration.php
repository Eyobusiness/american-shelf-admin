<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Configuration extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'configurations';

    protected $fillable = [
        'site_id',
        'duree_emprunt',
        'nombre_jours_grace',
        'duree_consultation',
        'quota_emprunt',
        'duree_reservation',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'duree_emprunt' => 'integer',
            'nombre_jours_grace' => 'integer',
            'duree_consultation' => 'integer',
            'quota_emprunt' => 'integer',
            'duree_reservation' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'configuration_id');
    }
}
