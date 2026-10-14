<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'reservations';

    protected $fillable = [
        'site_id',
        'usager_id',
        'livre_id',
        'date_reservation',
        'date_expiration',
        'statut',
        'observations',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_reservation' => 'datetime',
            'date_expiration' => 'datetime',
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

    public function livre(): BelongsTo
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }
}
