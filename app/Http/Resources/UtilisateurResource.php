<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UtilisateurResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'statut' => $this->statut,
            'derniere_connexion' => $this->derniere_connexion ? Carbon::parse($this->derniere_connexion)->toIso8601String() : null,
            'site' => $this->whenLoaded('site', function () {
                return [
                    'id' => $this->site->id,
                    'code' => $this->site->code,
                    'nom' => $this->site->nom,
                    'statut' => $this->site->statut,
                    'logo' => $this->site->logo,
                    'logo_url' => $this->site->logo ? (str_starts_with($this->site->logo, 'http') ? $this->site->logo : Storage::disk('public')->url($this->site->logo)) : null,
                ];
            }, [
                'id' => $this->site_id,
            ]),
            'role' => new RoleResource($this->whenLoaded('role')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
        ];
    }
}