<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Acquisition\StoreAcquisitionRequest;
use App\Http\Requests\Acquisition\UpdateAcquisitionRequest;
use App\Http\Resources\AcquisitionResource;
use App\Services\Document\AcquisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcquisitionController extends BaseApiController
{
    public function __construct(
        protected AcquisitionService $acquisitionService
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
        $filters = $request->only(['search', 'type', 'etat', 'date_acquisition', 'sort_by', 'sort_direction']);

        $acquisitions = $this->acquisitionService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($acquisitions, AcquisitionResource::class, 'Acquisitions récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $acquisition = $this->acquisitionService->findById($siteId, $id);

        if (!$acquisition) {
            return $this->errorResponse('Acquisition introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new AcquisitionResource($acquisition), 'Détail de l\'acquisition récupéré avec succès.');
    }

    public function store(StoreAcquisitionRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();
        $file = $request->file('piece_jointe');

        $acquisition = $this->acquisitionService->create($siteId, $validated, $file);

        return $this->successResponse(new AcquisitionResource($acquisition), 'Acquisition enregistrée avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateAcquisitionRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $acquisition = $this->acquisitionService->findById($siteId, $id);

        if (!$acquisition) {
            return $this->errorResponse('Acquisition introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $file = $request->file('piece_jointe');

        $updated = $this->acquisitionService->update($acquisition, $validated, $file);

        return $this->successResponse(new AcquisitionResource($updated), 'Acquisition mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $acquisition = $this->acquisitionService->findById($siteId, $id);

        if (!$acquisition) {
            return $this->errorResponse('Acquisition introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->acquisitionService->delete($acquisition, $userId);

        return $this->successResponse(null, 'Acquisition supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $acquisition = $this->acquisitionService->restore($siteId, $id);

        if (!$acquisition) {
            return $this->errorResponse('Acquisition supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new AcquisitionResource($acquisition), 'Acquisition restaurée avec succès.');
    }
}
