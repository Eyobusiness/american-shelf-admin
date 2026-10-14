<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    protected $table = 'menus';

    protected $fillable = [
        'site_id',
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

    public function sousMenus(): HasMany
    {
        return $this->hasMany(SousMenu::class, 'menu_id')->orderBy('order');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'menu_id');
    }
}
