<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = now();
        $dateOperation = $this->date_operation ? Carbon::parse($this->date_operation) : null;
        $dateRetourPrevue = $this->date_retour_prevue ? Carbon::parse($this->date_retour_prevue) : null;
        $dateRetourEffective = $this->date_retour_effective ? Carbon::parse($this->date_retour_effective) : null;

        // Calcul dynamique du retard
        $graceDays = (int) ($this->configuration?->nombre_jours_grace ?? 0);
        $dateLimite = $dateRetourPrevue
            ? ($this->type === 'EMPRUNT' ? $dateRetourPrevue->copy()->addDays($graceDays) : $dateRetourPrevue->copy())
            : null;

        $estEnRetard = false;
        $joursRetard = 0;

        if ($this->statut === 'EN_RETARD') {
            $estEnRetard = true;
            if ($dateRetourPrevue) {
                $refDate = $dateRetourEffective ?? $now;
                $joursRetard = max(0, (int) $dateRetourPrevue->diffInDays($refDate, false));
            }
        } elseif ($this->statut === 'EN_COURS' && $dateLimite && $now->isAfter($dateLimite)) {
            $estEnRetard = true;
            $joursRetard = max(0, (int) $dateRetourPrevue->diffInDays($now, false));
        }

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'configuration_id' => $this->configuration_id,
            'usager_id' => $this->usager_id,
            'exemplaire_id' => $this->exemplaire_id,
            'type' => $this->type,
            'date_operation' => $dateOperation ? $dateOperation->toIso8601String() : null,
            'date_retour_prevue' => $dateRetourPrevue ? $dateRetourPrevue->toIso8601String() : null,
            'date_retour_effective' => $dateRetourEffective ? $dateRetourEffective->toIso8601String() : null,
            'statut' => $this->statut,
            'observations' => $this->observations,
            'est_en_retard' => $estEnRetard,
            'jours_retard' => $joursRetard,
            'usager' => $this->whenLoaded('usager', function () {
                return [
                    'id' => $this->usager->id,
                    'numero' => $this->usager->numero,
                    'nom' => $this->usager->nom,
                    'prenom' => $this->usager->prenom,
                    'email' => $this->usager->email,
                    'contact' => $this->usager->contact,
                ];
            }),
            'exemplaire' => $this->whenLoaded('exemplaire', function () {
                return [
                    'id' => $this->exemplaire->id,
                    'code' => $this->exemplaire->code,
                    'etat' => $this->exemplaire->etat,
                    'statut' => $this->exemplaire->statut,
                    'localisation' => $this->exemplaire->localisation,
                    'livre' => $this->exemplaire->livre ? [
                        'id' => $this->exemplaire->livre->id,
                        'code' => $this->exemplaire->livre->code,
                        'titre' => $this->exemplaire->livre->titre,
                        'auteur' => $this->exemplaire->livre->auteur,
                        'isbn' => $this->exemplaire->livre->isbn,
                    ] : null,
                ];
            }),
            'configuration' => $this->whenLoaded('configuration', function () {
                return [
                    'id' => $this->configuration->id,
                    'duree_emprunt' => $this->configuration->duree_emprunt,
                    'nombre_jours_grace' => $this->configuration->nombre_jours_grace,
                    'duree_consultation' => $this->configuration->duree_consultation,
                    'quota_emprunt' => $this->configuration->quota_emprunt,
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
