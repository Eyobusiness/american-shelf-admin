<?php

namespace App\Http\Resources\SuperAdmin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SuperAdminSiteResource extends JsonResource
{
    /**
     * Transform the resource into an array for Super Admin view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $admin = $this->whenLoaded('utilisateurs', function () {
            $adminUser = $this->utilisateurs->first(function ($user) {
                return strtoupper($user->role?->code ?? '') === 'ADMIN';
            });

            if (!$adminUser) {
                return null;
            }

            return [
                'id' => $adminUser->id,
                'nom' => $adminUser->nom,
                'prenom' => $adminUser->prenom,
                'email' => $adminUser->email,
                'telephone' => $adminUser->telephone,
                'statut' => $adminUser->statut,
                'derniere_connexion' => $adminUser->derniere_connexion ? Carbon::parse($adminUser->derniere_connexion)->toIso8601String() : null,
            ];
        });

        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->nom,
            'logo' => $this->logo,
            'logo_url' => $this->logo ? (str_starts_with($this->logo, 'http') ? $this->logo : Storage::disk('public')->url($this->logo)) : null,
            'description' => $this->description,
            'adresse' => $this->adresse,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'statut' => $this->statut,
            'date_creation' => $this->date_creation ? Carbon::parse($this->date_creation)->format('Y-m-d') : null,
            'admin' => $admin,
            'configuration' => $this->whenLoaded('configuration', function () {
                if (!$this->configuration) {
                    return null;
                }
                return [
                    'id' => $this->configuration->id,
                    'duree_emprunt' => $this->configuration->duree_emprunt,
                    'nombre_jours_grace' => $this->configuration->nombre_jours_grace,
                    'duree_consultation' => $this->configuration->duree_consultation,
                    'quota_emprunt' => $this->configuration->quota_emprunt,
                    'duree_reservation' => $this->configuration->duree_reservation,
                ];
            }),
            'statistiques_rapides' => [
                'livres_count' => (int) ($this->livres_count ?? 0),
                'exemplaires_count' => (int) ($this->exemplaires_count ?? 0),
                'usagers_count' => (int) ($this->usager_sites_count ?? 0),
                'utilisateurs_count' => (int) ($this->utilisateurs_count ?? 0),
            ],
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
