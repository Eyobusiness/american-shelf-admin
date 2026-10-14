<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\SuperAdmin\GlobalDashboardFilterRequest;
use App\Services\SuperAdmin\GlobalStatisticsService;
use Illuminate\Http\JsonResponse;

class GlobalDashboardController extends BaseApiController
{
    public function __construct(
        protected GlobalStatisticsService $statisticsService
    ) {}

    /**
     * Obtenir les statistiques consolidées du tableau de bord global pour l'ensemble des sites.
     */
    public function index(GlobalDashboardFilterRequest $request): JsonResponse
    {
        $dateDebut = $request->query('date_debut');
        $dateFin = $request->query('date_fin');
        $topLimit = (int) $request->query('top_limit', 5);

        $data = $this->statisticsService->getGlobalDashboardData($dateDebut, $dateFin, $topLimit);

        return $this->successResponse($data, 'Statistiques consolidées du tableau de bord global récupérées avec succès.');
    }
}
