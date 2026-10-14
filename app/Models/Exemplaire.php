<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exemplaire extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'exemplaires';

    protected $fillable = [
        'site_id',
        'livre_id',
        'code',
        'etat',
        'statut',
        'localisation',
        'deleted_by',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function livre(): BelongsTo
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'exemplaire_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(ExemplaireMouvement::class, 'exemplaire_id');
    }
}
