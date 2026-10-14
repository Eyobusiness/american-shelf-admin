<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LivreResource extends JsonResource
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
            'code' => $this->code,
            'titre' => $this->titre,
            'auteur' => $this->auteur,
            'editeur' => $this->editeur,
            'annee_publication' => $this->annee_publication,
            'isbn' => $this->isbn,
            'categorie_livre_id' => $this->categorie_livre_id,
            'categorie' => $this->whenLoaded('categorieLivre', function () {
                return new CategorieLivreResource($this->categorieLivre);
            }),
            'image_principale' => $this->image_principale,
            'image_principale_url' => $this->image_principale ? $storage->url($this->image_principale) : null,
            'fichier_numerique' => $this->fichier_numerique,
            'fichier_numerique_url' => $this->fichier_numerique ? $storage->url($this->fichier_numerique) : null,
            'visible_en_ligne' => (bool) $this->visible_en_ligne,
            'statut' => $this->statut,
            // Donnée calculée dynamiquement, jamais persistée dans la table livres
            'nombre_exemplaires' => (int) ($this->exemplaires_count ?? ($this->relationLoaded('exemplaires') ? $this->exemplaires->count() : $this->exemplaires()->count())),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
