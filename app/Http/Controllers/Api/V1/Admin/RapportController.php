<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\Admin\ExportService;
use App\Services\Admin\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RapportController extends BaseApiController
{
    public function __construct(
        protected ReportService $reportService,
        protected ExportService $exportService
    ) {}

    /**
     * Résoudre l'identifiant du site courant avec isolation stricte.
     */
    protected function getSiteId(Request $request): string
    {
        $siteId = $request->attributes->get('current_site_id') ?? $request->user()?->site_id;
        if (!$siteId) {
            abort(Response::HTTP_FORBIDDEN, 'Contexte de site (tenant) manquant ou non autorisé.');
        }
        return (string) $siteId;
    }

    /**
     * 1. Rapport du Fonds Documentaire (JSON).
     */
    public function fondsDocumentaire(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getFondsDocumentaireReport($siteId, $request->validated());

        return $this->successResponse($data, 'Rapport du fonds documentaire récupéré avec succès.');
    }

    /**
     * Export du Fonds Documentaire (PDF / XLSX / CSV).
     */
    public function exportFondsDocumentaire(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getFondsDocumentaireReport($siteId, $request->validated());
        $exportData = $this->exportService->formatFondsDocumentaireData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_fonds_documentaire_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * 2. Rapport des Emprunts, Retours et Consultations (JSON).
     */
    public function emprunts(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getEmpruntsReport($siteId, $request->validated());

        return $this->successResponse($data, 'Rapport des emprunts et retours récupéré avec succès.');
    }

    /**
     * Export du Rapport des Emprunts (PDF / XLSX / CSV).
     */
    public function exportEmprunts(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getEmpruntsReport($siteId, $request->validated());
        $exportData = $this->exportService->formatEmpruntsData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_emprunts_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * 3. Rapport des Livres les plus empruntés (Top Livres) (JSON).
     */
    public function topLivres(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getTopLivresReport($siteId, $request->validated());

        return $this->successResponse($data, 'Classement des livres les plus empruntés récupéré avec succès.');
    }

    /**
     * Export du Rapport des Livres les plus empruntés (PDF / XLSX / CSV).
     */
    public function exportTopLivres(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getTopLivresReport($siteId, $request->validated());
        $exportData = $this->exportService->formatTopLivresData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_top_livres_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * 4. Rapport des Retards d'emprunts avec tranches d'ancienneté (JSON).
     */
    public function retards(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getRetardsReport($siteId, $request->validated());

        return $this->successResponse($data, 'Rapport des retards d\'emprunts récupéré avec succès.');
    }

    /**
     * Export du Rapport des Retards (PDF / XLSX / CSV).
     */
    public function exportRetards(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getRetardsReport($siteId, $request->validated());
        $exportData = $this->exportService->formatRetardsData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_retards_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * 5. Rapport des Acquisitions (JSON).
     */
    public function acquisitions(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getAcquisitionsReport($siteId, $request->validated());

        return $this->successResponse($data, 'Rapport des acquisitions récupéré avec succès.');
    }

    /**
     * Export du Rapport des Acquisitions (PDF / XLSX / CSV).
     */
    public function exportAcquisitions(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getAcquisitionsReport($siteId, $request->validated());
        $exportData = $this->exportService->formatAcquisitionsData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_acquisitions_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * 6. Rapport de Fréquentation (JSON).
     */
    public function frequentation(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getFrequentationReport($siteId, $request->validated());

        return $this->successResponse($data, 'Rapport de fréquentation récupéré avec succès.');
    }

    /**
     * 7. Rapport d'Inventaire Historique (Snapshots & Mouvements) (JSON).
     */
    public function inventaireHistorique(ReportFilterRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $data = $this->reportService->getInventaireHistoriqueReport($siteId, $request->validated());

        return $this->successResponse($data, 'Historique d\'inventaire récupéré avec succès.');
    }

    /**
     * Export de l'Historique d'Inventaire (PDF / XLSX / CSV).
     */
    public function exportInventaireHistorique(ReportFilterRequest $request): Response
    {
        $siteId = $this->getSiteId($request);
        $report = $this->reportService->getInventaireHistoriqueReport($siteId, $request->validated());
        $exportData = $this->exportService->formatInventaireData($report);
        $format = strtolower($request->query('format', 'xlsx'));
        $filename = 'rapport_inventaire_historique_' . date('Ymd_His');

        return match ($format) {
            'pdf' => $this->exportService->exportToPdf($filename, $exportData),
            'csv' => $this->exportService->exportToCsv($filename, $exportData),
            default => $this->exportService->exportToExcel($filename, $exportData),
        };
    }

    /**
     * Déclencher manuellement un Snapshot d'inventaire.
     */
    public function declencherSnapshot(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $date = $request->input('date');
        $userId = $request->user()?->id;

        $snapshot = $this->reportService->createInventaireSnapshot($siteId, $date, $userId);

        return $this->successResponse($snapshot, 'Snapshot d\'inventaire généré et enregistré avec succès.', 201);
    }
}
