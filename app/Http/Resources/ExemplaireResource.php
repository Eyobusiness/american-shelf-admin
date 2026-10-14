<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExemplaireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk('public');

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'livre_id' => $this->livre_id,
            'code' => $this->code,
            'etat' => $this->etat,
            'statut' => $this->statut,
            'localisation' => $this->localisation,
            'livre' => $this->whenLoaded('livre', function () use ($storage) {
                return [
                    'id' => $this->livre->id,
                    'code' => $this->livre->code,
                    'titre' => $this->livre->titre,
                    'auteur' => $this->livre->auteur,
                    'editeur' => $this->livre->editeur,
                    'annee_publication' => $this->livre->annee_publication,
                    'isbn' => $this->livre->isbn,
                    'image_principale' => $this->livre->image_principale,
                    'image_principale_url' => $this->livre->image_principale ? $storage->url($this->livre->image_principale) : null,
                    'categorie' => $this->livre->categorieLivre ? [
                        'id' => $this->livre->categorieLivre->id,
                        'code' => $this->livre->categorieLivre->code,
                        'nom' => $this->livre->categorieLivre->nom,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}