<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\SuperAdmin\AssignSiteAdminRequest;
use App\Http\Requests\SuperAdmin\StoreSiteWithAdminRequest;
use App\Http\Requests\SuperAdmin\UpdateSiteStatusRequest;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminSiteRequest;
use App\Http\Resources\SuperAdmin\SuperAdminSiteResource;
use App\Http\Resources\UtilisateurResource;
use App\Services\SuperAdmin\SuperAdminSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteManagementController extends BaseApiController
{
    public function __construct(
        protected SuperAdminSiteService $siteService
    ) {}

    /**
     * Liste paginée des sites pour le Super Admin.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only(['search', 'statut', 'sort_by', 'sort_direction']);

        $sites = $this->siteService->getAllSites($filters, $perPage);

        return $this->paginatedResponse($sites, SuperAdminSiteResource::class, 'Sites récupérés avec succès.');
    }

    /**
     * Consulter les détails complets d'un site.
     */
    public function show(string $id): JsonResponse
    {
        $site = $this->siteService->getSiteById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SuperAdminSiteResource($site), 'Détails du site récupérés avec succès.');
    }

    /**
     * Création atomique d'un site avec son premier administrateur et sa configuration.
     */
    public function store(StoreSiteWithAdminRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $siteData = [
            'code' => $validated['code'],
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'adresse' => $validated['adresse'] ?? null,
            'telephone' => $validated['telephone'] ?? null,
            'email' => $validated['email'] ?? null,
            'statut' => $validated['statut'] ?? 'ACTIF',
            'date_creation' => $validated['date_creation'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $siteData['logo'] = $request->file('logo');
        } elseif (!empty($validated['logo'])) {
            $siteData['logo'] = $validated['logo'];
        }

        $adminData = $validated['admin'];
        $configData = $validated['configuration'] ?? null;

        $result = $this->siteService->createSiteWithAdmin($siteData, $adminData, $configData);

        return $this->createdResponse(
            new SuperAdminSiteResource($result['site']),
            'Site, configuration et premier administrateur créés avec succès.'
        );
    }

    /**
     * Modifier les informations générales d'un site.
     */
    public function update(UpdateSuperAdminSiteRequest $request, string $id): JsonResponse
    {
        $site = $this->siteService->getSiteById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo');
        }

        $updatedSite = $this->siteService->updateSite($site, $data);

        return $this->successResponse(new SuperAdminSiteResource($updatedSite), 'Site mis à jour avec succès.');
    }

    /**
     * Modifier le statut d'un site (ACTIF, INACTIF, SUSPENDU).
     */
    public function updateStatus(UpdateSiteStatusRequest $request, string $id): JsonResponse
    {
        $site = $this->siteService->getSiteById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updatedSite = $this->siteService->updateSiteStatus($site, $request->validated('statut'));

        return $this->successResponse(new SuperAdminSiteResource($updatedSite), 'Statut du site modifié avec succès.');
    }

    /**
     * Affecter ou créer un administrateur sur un site existant.
     */
    public function assignAdmin(AssignSiteAdminRequest $request, string $id): JsonResponse
    {
        $site = $this->siteService->getSiteById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $adminUser = $this->siteService->assignSiteAdmin($site, $request->validated());

        return $this->createdResponse(
            new UtilisateurResource($adminUser->load('role', 'site')),
            'Administrateur assigné au site avec succès.'
        );
    }

    /**
     * Supprimer logiquement un site (Soft Delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $site = $this->siteService->getSiteById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user('api')?->id;
        $this->siteService->deleteSite($site, $userId);

        return $this->successResponse(null, 'Site supprimé avec succès.');
    }

    /**
     * Restaurer un site supprimé.
     */
    public function restore(string $id): JsonResponse
    {
        $site = $this->siteService->restoreSite($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable ou non supprimé.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SuperAdminSiteResource($site), 'Site restauré avec succès.');
    }
}
