<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acquisition extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'acquisitions';

    protected $fillable = [
        'site_id',
        'type',
        'date_acquisition',
        'provenance',
        'quantite',
        'cout',
        'reference_facture',
        'donateur',
        'nature_don',
        'destination',
        'etat',
        'observations',
        'piece_jointe',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date',
            'quantite' => 'integer',
            'cout' => 'decimal:2',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
