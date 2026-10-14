<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Exemplaire\StoreExemplaireBulkRequest;
use App\Http\Requests\Exemplaire\StoreExemplaireRequest;
use App\Http\Requests\Exemplaire\UpdateExemplaireRequest;
use App\Http\Resources\ExemplaireResource;
use App\Services\Document\ExemplaireService;
use App\Services\Document\LivreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExemplaireController extends BaseApiController
{
    public function __construct(
        protected ExemplaireService $exemplaireService,
        protected LivreService $livreService
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
            'livre_id',
            'etat',
            'statut',
            'localisation',
            'sort_by',
            'sort_direction',
        ]);

        $exemplaires = $this->exemplaireService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($exemplaires, ExemplaireResource::class, 'Exemplaires récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $exemplaire = $this->exemplaireService->findById($siteId, $id);

        if (!$exemplaire) {
            return $this->errorResponse('Exemplaire introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new ExemplaireResource($exemplaire), 'Détail de l\'exemplaire récupéré avec succès.');
    }

    public function store(StoreExemplaireRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $exemplaire = $this->exemplaireService->create($siteId, $validated);

        return $this->successResponse(new ExemplaireResource($exemplaire), 'Exemplaire créé avec succès.', Response::HTTP_CREATED);
    }

    /**
     * Création groupée via POST /api/v1/exemplaires/bulk
     */
    public function storeBulk(StoreExemplaireBulkRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();
        $livreId = (string) $validated['livre_id'];
        $nombre = (int) $validated['nombre'];

        $exemplaires = $this->exemplaireService->createBulk($siteId, $livreId, $nombre, $validated);

        return $this->successResponse(
            ExemplaireResource::collection($exemplaires),
            "{$nombre} exemplaires créés avec succès.",
            Response::HTTP_CREATED
        );
    }

    /**
     * Création groupée pour un livre via POST /api/v1/livres/{id}/exemplaires
     */
    public function storeBulkForLivre(StoreExemplaireBulkRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);

        // Vérification de l'existence du livre au sein du site courant (anti-IDOR)
        $livre = $this->livreService->findById($siteId, $id);
        if (!$livre) {
            return $this->errorResponse('Livre introuvable ou n\'appartenant pas à ce site.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $nombre = (int) ($validated['nombre'] ?? 1);

        $exemplaires = $this->exemplaireService->createBulk($siteId, $livre->id, $nombre, $validated);

        return $this->successResponse(
            ExemplaireResource::collection($exemplaires),
            "{$nombre} exemplaires créés avec succès pour le livre {$livre->code}.",
            Response::HTTP_CREATED
        );
    }

    public function update(UpdateExemplaireRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $exemplaire = $this->exemplaireService->findById($siteId, $id);

        if (!$exemplaire) {
            return $this->errorResponse('Exemplaire introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $updated = $this->exemplaireService->update($exemplaire, $validated);

        return $this->successResponse(new ExemplaireResource($updated), 'Exemplaire mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $exemplaire = $this->exemplaireService->findById($siteId, $id);

        if (!$exemplaire) {
            return $this->errorResponse('Exemplaire introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->exemplaireService->delete($exemplaire, $userId);

        return $this->successResponse(null, 'Exemplaire supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $exemplaire = $this->exemplaireService->restore($siteId, $id);

        if (!$exemplaire) {
            return $this->errorResponse('Exemplaire supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new ExemplaireResource($exemplaire), 'Exemplaire restauré avec succès.');
    }
}
