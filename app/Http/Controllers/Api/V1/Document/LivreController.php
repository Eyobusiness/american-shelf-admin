<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Livre\StoreLivreRequest;
use App\Http\Requests\Livre\UpdateLivreRequest;
use App\Http\Resources\LivreResource;
use App\Services\Document\LivreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LivreController extends BaseApiController
{
    public function __construct(
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
            'categorie_livre_id',
            'statut',
            'visible_en_ligne',
            'auteur',
            'annee_publication',
            'sort_by',
            'sort_direction',
        ]);

        $livres = $this->livreService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($livres, LivreResource::class, 'Livres récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $livre = $this->livreService->findById($siteId, $id);

        if (!$livre) {
            return $this->errorResponse('Livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new LivreResource($livre), 'Détail du livre récupéré avec succès.');
    }

    public function store(StoreLivreRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();
        $image = $request->file('image_principale');
        $fichier = $request->file('fichier_numerique');

        $livre = $this->livreService->create($siteId, $validated, $image, $fichier);

        return $this->successResponse(new LivreResource($livre), 'Livre enregistré avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateLivreRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $livre = $this->livreService->findById($siteId, $id);

        if (!$livre) {
            return $this->errorResponse('Livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $image = $request->file('image_principale');
        $fichier = $request->file('fichier_numerique');

        $updated = $this->livreService->update($livre, $validated, $image, $fichier);

        return $this->successResponse(new LivreResource($updated), 'Livre mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $livre = $this->livreService->findById($siteId, $id);

        if (!$livre) {
            return $this->errorResponse('Livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->livreService->delete($livre, $userId);

        return $this->successResponse(null, 'Livre supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $livre = $this->livreService->restore($siteId, $id);

        if (!$livre) {
            return $this->errorResponse('Livre supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new LivreResource($livre), 'Livre restauré avec succès.');
    }
}
