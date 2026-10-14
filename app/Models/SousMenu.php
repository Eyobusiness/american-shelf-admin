<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SousMenu extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'sous_menus';

    protected $fillable = [
        'site_id',
        'menu_id',
        'code',
        'libelle',
        'icon',
        'path',
        'order',
        'reference',
        'permission',
        'statut',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'sous_menu_id');
    }
}
