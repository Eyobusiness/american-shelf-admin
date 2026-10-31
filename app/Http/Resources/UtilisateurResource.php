<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use DateTimeInterface;
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
            'derniere_connexion' => $this->formatDate($this->derniere_connexion),
            'site' => $this->whenLoaded('site', function () {
                if (!$this->site) {
                    return null;
                }
                /** @var \Illuminate\Filesystem\FilesystemAdapter $publicStorage */
                $publicStorage = Storage::disk('public');

                return [
                    'id' => $this->site->id,
                    'code' => $this->site->code,
                    'nom' => $this->site->nom,
                    'statut' => $this->site->statut,
                    'logo' => $this->site->logo,
                    'logo_url' => $this->site->logo ? (str_starts_with($this->site->logo, 'http') ? $this->site->logo : $publicStorage->url($this->site->logo)) : null,
                ];
            }, $this->site_id ? ['id' => $this->site_id] : null),
            'role' => new RoleResource($this->whenLoaded('role')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }

    /**
     * Sérialise de manière robuste et sécurisée une date au format ISO 8601.
     * Supporte les objets DateTimeInterface/Carbon, les chaînes de caractères et les valeurs nulles.
     *
     * @param mixed $date
     * @return string|null
     */
    protected function formatDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format(DateTimeInterface::ATOM);
        }

        if (is_string($date) && trim($date) !== '') {
            try {
                return Carbon::parse($date)->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}