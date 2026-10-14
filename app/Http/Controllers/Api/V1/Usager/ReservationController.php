<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Reservation\AnnulerReservationRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Usager\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends BaseApiController
{
    public function __construct(
        protected ReservationService $reservationService
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
            'statut',
            'usager_id',
            'livre_id',
            'actives',
            'date_debut',
            'date_fin',
            'sort_by',
            'sort_direction',
            'order',
        ]);

        $reservations = $this->reservationService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($reservations, ReservationResource::class, 'Réservations récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $reservation = $this->reservationService->findById($siteId, $id);

        if (!$reservation) {
            return $this->errorResponse('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new ReservationResource($reservation), 'Détail de la réservation récupéré avec succès.');
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $reservation = $this->reservationService->create($siteId, $validated);

        return $this->successResponse(new ReservationResource($reservation), 'Réservation enregistrée avec succès.', Response::HTTP_CREATED);
    }

    public function annuler(AnnulerReservationRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $reservation = $this->reservationService->annuler($siteId, $id, $validated);

        return $this->successResponse(new ReservationResource($reservation), 'Réservation annulée avec succès.');
    }

    public function update(UpdateReservationRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $reservation = $this->reservationService->findById($siteId, $id);

        if (!$reservation) {
            return $this->errorResponse('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $updated = $this->reservationService->update($reservation, $validated);

        return $this->successResponse(new ReservationResource($updated), 'Réservation mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $reservation = $this->reservationService->findById($siteId, $id);

        if (!$reservation) {
            return $this->errorResponse('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->reservationService->delete($reservation, $userId);

        return $this->successResponse(null, 'Réservation supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $reservation = $this->reservationService->restore($siteId, $id);

        if (!$reservation) {
            return $this->errorResponse('Réservation supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new ReservationResource($reservation), 'Réservation restaurée avec succès.');
    }
}
