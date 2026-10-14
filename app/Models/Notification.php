<?php

namespace App\Models;

use App\Traits\AuditableSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory, HasUuids, AuditableSoftDeletes;

    // Statuts d'envoi e-mail
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_ENVOYE = 'ENVOYE';
    public const STATUT_ECHEC = 'ECHEC';

    // Types de destinataires
    public const DESTINATAIRE_ADMIN = 'ADMIN';
    public const DESTINATAIRE_USAGER = 'USAGER';

    // Types de notifications
    public const TYPE_EMAIL = 'EMAIL';
    public const TYPE_RESERVATION_CREEE = 'RESERVATION_CREEE';
    public const TYPE_RESERVATION_EXPIREE = 'RESERVATION_EXPIREE';
    public const TYPE_RESERVATION_ANNULEE = 'RESERVATION_ANNULEE';
    public const TYPE_EMPRUNT_EN_RETARD = 'EMPRUNT_EN_RETARD';
    public const TYPE_RETOUR_EFFECTUE = 'RETOUR_EFFECTUE';

    public static function getStatuts(): array
    {
        return [
            self::STATUT_EN_ATTENTE,
            self::STATUT_ENVOYE,
            self::STATUT_ECHEC,
        ];
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_EMAIL,
            self::TYPE_RESERVATION_CREEE,
            self::TYPE_RESERVATION_EXPIREE,
            self::TYPE_RESERVATION_ANNULEE,
            self::TYPE_EMPRUNT_EN_RETARD,
            self::TYPE_RETOUR_EFFECTUE,
        ];
    }

    protected $table = 'notifications';

    protected $fillable = [
        'site_id',
        'usager_id',
        'utilisateur_id',
        'destinataire_type',
        'type',
        'objet',
        'message',
        'email_destinataire',
        'date_envoi',
        'date_lecture',
        'entite_type',
        'entite_id',
        'action_url',
        'statut',
        'deleted_by',
    ];

    protected $appends = [
        'is_lu',
    ];

    protected function casts(): array
    {
        return [
            'date_envoi' => 'datetime',
            'date_lecture' => 'datetime',
        ];
    }

    public function getIsLuAttribute(): bool
    {
        return !is_null($this->date_lecture);
    }

    public function isLu(): bool
    {
        return !is_null($this->date_lecture);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function usager(): BelongsTo
    {
        return $this->belongsTo(Usager::class, 'usager_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    /**
     * Scope pour les notifications non lues.
     */
    public function scopeNonLues(Builder $query): Builder
    {
        return $query->whereNull('date_lecture');
    }

    /**
     * Scope pour filtrer les notifications destinées au personnel administratif du site.
     */
    public function scopePourAdmin(Builder $query, string $siteId, ?string $utilisateurId = null): Builder
    {
        return $query->where('site_id', $siteId)
            ->where('destinataire_type', self::DESTINATAIRE_ADMIN)
            ->where(function (Builder $q) use ($utilisateurId) {
                $q->whereNull('utilisateur_id');
                if ($utilisateurId) {
                    $q->orWhere('utilisateur_id', $utilisateurId);
                }
            });
    }
}