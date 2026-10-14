<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\JournalActiviteResource;
use App\Services\Admin\JournalActiviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JournalActiviteController extends BaseApiController
{
    public function __construct(
        protected JournalActiviteService $journalActiviteService
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
     * Liste paginée des activités du site courant.
     */
    public function index(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only([
            'search',
            'module',
            'action',
            'utilisateur_id',
            'date',
            'date_debut',
            'date_fin',
            'sort_by',
            'sort_direction',
            'order',
        ]);

        $activites = $this->journalActiviteService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($activites, JournalActiviteResource::class, 'Journal d\'activité récupéré avec succès.');
    }

    /**
     * Détail d'une entrée du journal d'activité.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $activite = $this->journalActiviteService->findById($siteId, $id);

        if (!$activite) {
            return $this->errorResponse('Entrée du journal d\'activité introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new JournalActiviteResource($activite), 'Détail de l\'activité récupéré avec succès.');
    }
}
