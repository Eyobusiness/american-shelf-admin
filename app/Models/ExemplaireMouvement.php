<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExemplaireMouvement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'exemplaire_mouvements';

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'exemplaire_id',
        'ancien_etat',
        'nouveau_etat',
        'ancien_statut',
        'nouveau_statut',
        'motif',
        'operation_id',
        'utilisateur_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function exemplaire(): BelongsTo
    {
        return $this->belongsTo(Exemplaire::class, 'exemplaire_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}
