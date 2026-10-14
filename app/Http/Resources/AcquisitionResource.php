<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AcquisitionResource extends JsonResource
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
            'type' => $this->type,
            'date_acquisition' => $this->date_acquisition ? Carbon::parse($this->date_acquisition)->format('Y-m-d') : null,
            'provenance' => $this->provenance,
            'quantite' => $this->quantite,
            'cout' => $this->cout !== null ? (float) $this->cout : null,
            'reference_facture' => $this->reference_facture,
            'donateur' => $this->donateur,
            'nature_don' => $this->nature_don,
            'destination' => $this->destination,
            'etat' => $this->etat,
            'observations' => $this->observations,
            'piece_jointe' => $this->piece_jointe ? Storage::disk('public')->url($this->piece_jointe) : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
            'deleted_by' => $this->deleted_by,
        ];
    }
}

