<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Configuration\SaveConfigurationRequest;
use App\Http\Resources\ConfigurationResource;
use App\Services\Admin\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigurationController extends BaseApiController
{
    public function __construct(
        protected ConfigurationService $configurationService
    ) {}

    protected function getSiteId(Request $request): string
    {
        $siteId = $request->attributes->get('current_site_id') ?? $request->user()?->site_id;
        if (!$siteId) {
            abort(Response::HTTP_FORBIDDEN, 'Contexte de site (tenant) manquant ou non autorisé.');
        }
        return (string) $siteId;
    }

    /**
     * Récupérer la configuration du site.
     */
    public function show(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $config = $this->configurationService->getForSite($siteId);

        return $this->successResponse(new ConfigurationResource($config), 'Configuration récupérée avec succès.');
    }

    /**
     * Mettre à jour / enregistrer la configuration du site.
     */
    public function save(SaveConfigurationRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $config = $this->configurationService->saveForSite($siteId, $request->validated());

        return $this->successResponse(new ConfigurationResource($config), 'Configuration enregistrée avec succès.');
    }
}
