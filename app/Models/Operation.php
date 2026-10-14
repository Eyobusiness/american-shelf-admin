<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'operations';

    protected $fillable = [
        'site_id',
        'configuration_id',
        'usager_id',
        'exemplaire_id',
        'type',
        'date_operation',
        'date_retour_prevue',
        'date_retour_effective',
        'statut',
        'observations',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_operation' => 'datetime',
            'date_retour_prevue' => 'datetime',
            'date_retour_effective' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(Configuration::class, 'configuration_id');
    }

    public function usager(): BelongsTo
    {
        return $this->belongsTo(Usager::class, 'usager_id');
    }

    public function exemplaire(): BelongsTo
    {
        return $this->belongsTo(Exemplaire::class, 'exemplaire_id');
    }
}
