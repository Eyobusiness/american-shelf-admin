<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalActiviteResource extends JsonResource
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
            'site_id' => $this->site_id,
            'utilisateur_id' => $this->utilisateur_id,
            'action' => $this->action,
            'module' => $this->module,
            'reference' => $this->reference,
            'description' => $this->description,
            'adresse_ip' => $this->adresse_ip,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'utilisateur' => $this->whenLoaded('utilisateur', function () {
                if (!$this->utilisateur) {
                    return null;
                }
                return [
                    'id' => $this->utilisateur->id,
                    'nom' => $this->utilisateur->nom,
                    'prenom' => $this->utilisateur->prenom,
                    'email' => $this->utilisateur->email,
                ];
            }),
        ];
    }
}
