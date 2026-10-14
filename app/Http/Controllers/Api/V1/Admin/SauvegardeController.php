<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\SauvegardeResource;
use App\Services\Admin\SauvegardeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SauvegardeController extends BaseApiController
{
    public function __construct(
        protected SauvegardeService $sauvegardeService
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
     * Liste paginée des sauvegardes.
     */
    public function index(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only([
            'search',
            'statut',
            'date_debut',
            'date_fin',
            'sort_by',
            'sort_direction',
            'order',
        ]);

        $sauvegardes = $this->sauvegardeService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($sauvegardes, SauvegardeResource::class, 'Sauvegardes récupérées avec succès.');
    }

    /**
     * Détail d'une sauvegarde.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sauvegarde = $this->sauvegardeService->findById($siteId, $id);

        if (!$sauvegarde) {
            return $this->errorResponse('Sauvegarde introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SauvegardeResource($sauvegarde), 'Détail de la sauvegarde récupéré avec succès.');
    }

    /**
     * Suppression logique d'une sauvegarde.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sauvegarde = $this->sauvegardeService->findById($siteId, $id);

        if (!$sauvegarde) {
            return $this->errorResponse('Sauvegarde introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->sauvegardeService->delete($sauvegarde, $userId);

        return $this->successResponse(null, 'Sauvegarde supprimée avec succès.');
    }

    /**
     * Restauration d'une sauvegarde supprimée logiquement.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sauvegarde = $this->sauvegardeService->restore($siteId, $id);

        if (!$sauvegarde) {
            return $this->errorResponse('Sauvegarde supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SauvegardeResource($sauvegarde), 'Sauvegarde restaurée avec succès.');
    }
}
