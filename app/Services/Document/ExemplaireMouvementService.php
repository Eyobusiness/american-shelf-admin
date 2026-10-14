<?php

namespace App\Services\Document;

use App\Models\Exemplaire;
use App\Models\ExemplaireMouvement;

class ExemplaireMouvementService
{
    /**
     * Enregistre un mouvement d'exemplaire (changement d'état physique ou de statut opérationnel).
     */
    public function log(
        Exemplaire $exemplaire,
        string $nouveauStatut,
        string $nouveauEtat,
        ?string $ancienStatut = null,
        ?string $ancienEtat = null,
        ?string $motif = null,
        ?string $operationId = null,
        ?string $utilisateurId = null
    ): ExemplaireMouvement {
        return ExemplaireMouvement::create([
            'site_id' => $exemplaire->site_id,
            'exemplaire_id' => $exemplaire->id,
            'ancien_etat' => $ancienEtat,
            'nouveau_etat' => $nouveauEtat,
            'ancien_statut' => $ancienStatut,
            'nouveau_statut' => $nouveauStatut,
            'motif' => $motif,
            'operation_id' => $operationId,
            'utilisateur_id' => $utilisateurId ?? auth('api')->id(),
            'created_at' => now(),
        ]);
    }
}
