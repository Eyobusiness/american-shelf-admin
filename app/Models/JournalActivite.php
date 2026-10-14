<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalActivite extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'journaux_activites';

    public const UPDATED_AT = null;

    protected $fillable = [
        'site_id',
        'utilisateur_id',
        'action',
        'module',
        'reference',
        'description',
        'adresse_ip',
        'created_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}
