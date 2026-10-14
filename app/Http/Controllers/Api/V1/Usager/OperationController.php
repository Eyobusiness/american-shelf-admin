<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Operation\RetourOperationRequest;
use App\Http\Requests\Operation\StoreOperationRequest;
use App\Http\Requests\Operation\UpdateOperationRequest;
use App\Http\Resources\OperationResource;
use App\Services\Usager\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationController extends BaseApiController
{
    public function __construct(
        protected OperationService $operationService
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
            'type',
            'statut',
            'usager_id',
            'exemplaire_id',
            'date_operation_debut',
            'date_operation_fin',
            'en_retard',
            'sort_by',
            'order',
        ]);

        $operations = $this->operationService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($operations, OperationResource::class, 'Opérations récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $operation = $this->operationService->findById($siteId, $id);

        if (!$operation) {
            return $this->errorResponse('Opération introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new OperationResource($operation), "Détail de l'opération récupéré avec succès.");
    }

    public function store(StoreOperationRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $operation = $this->operationService->create($siteId, $validated);

        return $this->successResponse(new OperationResource($operation), 'Opération enregistrée avec succès.', Response::HTTP_CREATED);
    }

    public function retour(RetourOperationRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $operation = $this->operationService->retour($siteId, $id, $validated);

        return $this->successResponse(new OperationResource($operation), "Retour de l'opération enregistré avec succès.");
    }

    public function update(UpdateOperationRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $operation = $this->operationService->findById($siteId, $id);

        if (!$operation) {
            return $this->errorResponse('Opération introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $updated = $this->operationService->update($operation, $validated);

        return $this->successResponse(new OperationResource($updated), 'Opération mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $operation = $this->operationService->findById($siteId, $id);

        if (!$operation) {
            return $this->errorResponse('Opération introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->operationService->delete($operation, $userId);

        return $this->successResponse(null, 'Opération supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $operation = $this->operationService->restore($siteId, $id);

        if (!$operation) {
            return $this->errorResponse('Opération supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new OperationResource($operation), 'Opération restaurée avec succès.');
    }
}
