<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\DashboardFilterRequest;
use App\Services\Admin\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends BaseApiController
{
    public function __construct(
        protected StatisticsService $statisticsService
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
     * Obtenir les statistiques consolidées du tableau de bord pour le site connecté.
     */
    public function index(DashboardFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $dateDebut = $request->query('date_debut');
        $dateFin = $request->query('date_fin');
        $topLimit = (int) $request->query('top_limit', 5);

        $data = $this->statisticsService->getDashboardData($siteId, $dateDebut, $dateFin, $topLimit);

        return $this->successResponse($data, 'Statistiques du tableau de bord récupérées avec succès.');
    }
}
