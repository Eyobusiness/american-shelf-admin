<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Usager\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends BaseApiController
{
    public function __construct(
        protected NotificationService $notificationService
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
            'type',
            'usager_id',
            'destinataire_type',
            'non_lues',
            'date',
            'date_debut',
            'date_fin',
            'sort_by',
            'sort_direction',
            'order',
        ]);

        // Filtrage explicite si fourni par la requête
        if ($request->has('destinataire_type')) {
            $filters['destinataire_type'] = $request->query('destinataire_type');
        }

        $notifications = $this->notificationService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($notifications, NotificationResource::class, 'Notifications récupérées avec succès.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $userId = $request->user()?->id;
        $destinataireType = $request->query('destinataire_type', Notification::DESTINATAIRE_ADMIN);

        $count = $this->notificationService->getUnreadCount($siteId, $userId, $destinataireType);

        return $this->successResponse(['count' => $count], 'Compteur de notifications non lues récupéré avec succès.');
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $userId = $request->user()?->id;

        $notification = $this->notificationService->markAsRead($siteId, $id, $userId);

        return $this->successResponse(new NotificationResource($notification), 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $userId = $request->user()?->id;
        $destinataireType = $request->input('destinataire_type', Notification::DESTINATAIRE_ADMIN);

        $affected = $this->notificationService->markAllAsRead($siteId, $userId, $destinataireType);

        return $this->successResponse(['affected' => $affected], 'Toutes les notifications ont été marquées comme lues.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $notification = $this->notificationService->findById($siteId, $id);

        if (!$notification) {
            return $this->errorResponse('Notification introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new NotificationResource($notification), 'Détail de la notification récupéré avec succès.');
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $notification = $this->notificationService->create($siteId, $validated);

        return $this->successResponse(new NotificationResource($notification), 'Notification créée et envoyée avec succès.', Response::HTTP_CREATED);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $notification = $this->notificationService->findById($siteId, $id);

        if (!$notification) {
            return $this->errorResponse('Notification introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->notificationService->delete($notification, $userId);

        return $this->successResponse(null, 'Notification supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $notification = $this->notificationService->restore($siteId, $id);

        if (!$notification) {
            return $this->errorResponse('Notification supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new NotificationResource($notification), 'Notification restaurée avec succès.');
    }
}