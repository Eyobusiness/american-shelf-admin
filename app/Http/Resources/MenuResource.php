<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
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
            'code' => $this->code,
            'libelle' => $this->libelle,
            'icon' => $this->icon,
            'path' => $this->path,
            'order' => $this->order,
            'reference' => $this->reference,
            'permission' => $this->permission,
            'statut' => $this->statut,
            'sous_menus' => SousMenuResource::collection($this->whenLoaded('sousMenus')),
            'sousMenus' => SousMenuResource::collection($this->whenLoaded('sousMenus')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
