<?php

namespace App\Services\Admin;

use App\Models\Configuration;
use Illuminate\Support\Facades\DB;

class ConfigurationService
{
    /**
     * Récupérer la configuration pour un site, ou retourner les valeurs par défaut.
     */
    public function getForSite(string $siteId): Configuration
    {
        return Configuration::firstOrCreate(
            ['site_id' => $siteId],
            [
                'duree_emprunt' => 14,
                'nombre_jours_grace' => 3,
                'duree_consultation' => 2,
                'quota_emprunt' => 3,
                'duree_reservation' => 2,
            ]
        );
    }

    /**
     * Sauvegarder (créer ou mettre à jour) la configuration d'un site.
     */
    public function saveForSite(string $siteId, array $data): Configuration
    {
        return DB::transaction(function () use ($siteId, $data) {
            $config = Configuration::where('site_id', $siteId)->first();

            if ($config) {
                $config->update($data);
                return $config->fresh();
            }

            $data['site_id'] = $siteId;
            return Configuration::create($data);
        });
    }
}
