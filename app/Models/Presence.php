<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'presences';

    protected $fillable = [
        'site_id',
        'usager_id',
        'date_presence',
        'heure_arrivee',
        'heure_depart',
        'motif',
        'observations',
        'deleted_by',
    ];

    protected $appends = [
        'statut',
        'duree_minutes',
        'duree_formatee',
    ];

    protected function casts(): array
    {
        return [
            'date_presence' => 'date',
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

    public function getStatutAttribute(): string
    {
        return empty($this->heure_depart) ? 'EN_COURS' : 'TERMINE';
    }

    public function getDureeMinutesAttribute(): ?int
    {
        if (empty($this->heure_arrivee) || empty($this->heure_depart)) {
            return null;
        }

        try {
            $arrivee = Carbon::parse($this->heure_arrivee);
            $depart = Carbon::parse($this->heure_depart);
            if ($depart->lt($arrivee)) {
                return null;
            }
            return (int) $arrivee->diffInMinutes($depart);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getDureeFormateeAttribute(): ?string
    {
        $minutes = $this->duree_minutes;
        if ($minutes === null) {
            return null;
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return "{$m} min";
        }
        if ($m === 0) {
            return "{$h} h";
        }
        return "{$h} h {$m} min";
    }
}
