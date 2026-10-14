<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Presence\StorePresenceRequest;
use App\Http\Requests\Presence\UpdatePresenceRequest;
use App\Http\Resources\PresenceResource;
use App\Services\Usager\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresenceController extends BaseApiController
{
    public function __construct(
        protected PresenceService $presenceService
    ) {}

    protected function getSiteId(Request $request): string
    {
        $siteId = $request->attributes->get('current_site_id') ?? $request->user()?->site_id;
        if (!$siteId) {
            abort(Response::HTTP_FORBIDDEN, 'Contexte de site (tenant) manquant ou non autorisé.');
        }
        return (string) $siteId;
    }

    public function index(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only([
            'search',
            'date_presence',
            'date_debut',
            'date_fin',
            'annee',
            'mois',
            'statut',
            'usager_id',
            'anonyme',
            'motif',
            'sort_by',
            'sort_direction',
        ]);

        $presences = $this->presenceService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($presences, PresenceResource::class, 'Présences récupérées avec succès.');
    }

    public function statistiques(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $filters = $request->only([
            'annee',
            'mois',
            'usager_id',
            'date_debut',
            'date_fin',
        ]);

        $stats = $this->presenceService->getStatistiques($siteId, $filters);

        return $this->successResponse($stats, 'Statistiques de fréquentation récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $presence = $this->presenceService->findById($siteId, $id);

        if (!$presence) {
            return $this->errorResponse('Présence introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new PresenceResource($presence), 'Détail de la présence récupéré avec succès.');
    }

    public function store(StorePresenceRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        try {
            $presence = $this->presenceService->create($siteId, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse(new PresenceResource($presence), 'Présence enregistrée avec succès.', Response::HTTP_CREATED);
    }

    public function sortie(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $presence = $this->presenceService->findById($siteId, $id);

        if (!$presence) {
            return $this->errorResponse('Présence introuvable.', Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'heure_depart' => ['nullable', 'date_format:H:i,H:i:s'],
            'observations' => ['nullable', 'string'],
        ], [
            'heure_depart.date_format' => 'L\'heure de départ doit respecter le format HH:MM ou HH:MM:SS.',
        ]);

        try {
            $updated = $this->presenceService->enregistrerSortie(
                $presence,
                $request->input('heure_depart'),
                $request->input('observations')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse(new PresenceResource($updated), 'Sortie enregistrée avec succès.');
    }

    public function update(UpdatePresenceRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $presence = $this->presenceService->findById($siteId, $id);

        if (!$presence) {
            return $this->errorResponse('Présence introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        try {
            $updated = $this->presenceService->update($presence, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse(new PresenceResource($updated), 'Présence mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $presence = $this->presenceService->findById($siteId, $id);

        if (!$presence) {
            return $this->errorResponse('Présence introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->presenceService->delete($presence, $userId);

        return $this->successResponse(null, 'Présence supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $presence = $this->presenceService->restore($siteId, $id);

        if (!$presence) {
            return $this->errorResponse('Présence supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new PresenceResource($presence), 'Présence restaurée avec succès.');
    }
}