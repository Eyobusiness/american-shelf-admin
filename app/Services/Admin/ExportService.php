<?php

namespace App\Services\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportService
{
    /**
     * Génère un export PDF prêt au téléchargement.
     */
    public function exportToPdf(string $filename, array $viewData): Response
    {
        $pdf = Pdf::loadView('reports.pdf', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Génère un fichier Excel (.xlsx) avec OpenSpout.
     */
    public function exportToExcel(string $filename, array $exportData): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'rep_xlsx_') . '.xlsx';
        $writer = new XlsxWriter();
        $writer->openToFile($tempPath);

        // Styles
        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('1E3A8A');

        $titleStyle = (new Style())
            ->setFontBold()
            ->setFontSize(14)
            ->setFontColor('1E3A8A');

        $sectionStyle = (new Style())
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor('1E3A8A');

        $kpiHeaderStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('F1F5F9');

        // Titre & En-tête
        $writer->addRow(Row::fromValues([$exportData['titre'] ?? 'Rapport'], $titleStyle));
        $writer->addRow(Row::fromValues(['Site', $exportData['site_nom'] ?? 'Tous']));
        if (!empty($exportData['periode'])) {
            $writer->addRow(Row::fromValues(['Période', $exportData['periode']]));
        }
        $writer->addRow(Row::fromValues(['Généré le', $exportData['date_generation'] ?? now()->toDateTimeString()]));
        $writer->addRow(Row::fromValues([])); // Ligne vide

        // Indicateurs KPIs
        if (!empty($exportData['kpis'])) {
            $writer->addRow(Row::fromValues(['INDICATEURS CLÉS'], $sectionStyle));
            $kpiHeaders = array_keys($exportData['kpis']);
            $kpiValues = array_values($exportData['kpis']);
            $writer->addRow(Row::fromValues($kpiHeaders, $kpiHeaderStyle));
            $writer->addRow(Row::fromValues($kpiValues));
            $writer->addRow(Row::fromValues([]));
        }

        // Tableaux de données
        if (!empty($exportData['tables'])) {
            foreach ($exportData['tables'] as $table) {
                $writer->addRow(Row::fromValues([$table['titre'] ?? 'Tableau'], $sectionStyle));
                if (!empty($table['headers'])) {
                    $writer->addRow(Row::fromValues($table['headers'], $headerStyle));
                }
                if (!empty($table['rows'])) {
                    foreach ($table['rows'] as $rowData) {
                        $writer->addRow(Row::fromValues($rowData));
                    }
                }
                $writer->addRow(Row::fromValues([]));
            }
        }

        $writer->close();

        return response()->download($tempPath, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Génère un fichier CSV avec OpenSpout.
     */
    public function exportToCsv(string $filename, array $exportData): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'rep_csv_') . '.csv';
        $writer = new CsvWriter();
        $writer->openToFile($tempPath);

        // En-têtes métadonnées
        $writer->addRow(Row::fromValues([$exportData['titre'] ?? 'Rapport']));
        $writer->addRow(Row::fromValues(['Site', $exportData['site_nom'] ?? 'Tous']));
        if (!empty($exportData['periode'])) {
            $writer->addRow(Row::fromValues(['Période', $exportData['periode']]));
        }
        $writer->addRow(Row::fromValues(['Généré le', $exportData['date_generation'] ?? now()->toDateTimeString()]));
        $writer->addRow(Row::fromValues([]));

        // KPIs
        if (!empty($exportData['kpis'])) {
            $writer->addRow(Row::fromValues(['INDICATEURS CLÉS']));
            $writer->addRow(Row::fromValues(array_keys($exportData['kpis'])));
            $writer->addRow(Row::fromValues(array_values($exportData['kpis'])));
            $writer->addRow(Row::fromValues([]));
        }

        // Tableaux
        if (!empty($exportData['tables'])) {
            foreach ($exportData['tables'] as $table) {
                $writer->addRow(Row::fromValues([$table['titre'] ?? 'Tableau']));
                if (!empty($table['headers'])) {
                    $writer->addRow(Row::fromValues($table['headers']));
                }
                if (!empty($table['rows'])) {
                    foreach ($table['rows'] as $rowData) {
                        $writer->addRow(Row::fromValues($rowData));
                    }
                }
                $writer->addRow(Row::fromValues([]));
            }
        }

        $writer->close();

        return response()->download($tempPath, "{$filename}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Convertit les données du Fonds Documentaire en structure exportable.
     */
    public function formatFondsDocumentaireData(array $report): array
    {
        $kpis = [
            'Références bibliographiques' => $report['synthese']['total_references_bibliographiques'] ?? 0,
            'Total exemplaires' => $report['synthese']['total_exemplaires'] ?? 0,
            'Disponibles' => $report['synthese']['exemplaires_disponibles'] ?? 0,
            'Empruntés' => $report['synthese']['exemplaires_empruntes'] ?? 0,
            'En consultation' => $report['synthese']['exemplaires_en_consultation'] ?? 0,
            'Réservés' => $report['synthese']['exemplaires_reserves'] ?? 0,
            'Indisponibles' => $report['synthese']['exemplaires_indisponibles'] ?? 0,
            'Perdus (physiques)' => $report['synthese']['exemplaires_perdus'] ?? 0,
        ];

        // Tableau par catégorie
        $catRows = [];
        foreach ($report['repartition_par_categorie'] ?? [] as $c) {
            $catRows[] = [
                $c['categorie'] ?? 'Sans catégorie',
                $c['total_livres'] ?? 0,
                $c['total_exemplaires'] ?? 0,
                $c['disponibles'] ?? 0,
                $c['empruntes'] ?? 0,
                $c['perdus'] ?? 0,
            ];
        }

        // Tableau des livres
        $livreRows = [];
        foreach ($report['livres']['data'] ?? [] as $l) {
            $livreRows[] = [
                $l['code'] ?? '',
                $l['titre'] ?? '',
                $l['auteur'] ?? '',
                $l['categorie'] ?? '',
                $l['total_exemplaires'] ?? 0,
                $l['disponibles'] ?? 0,
                $l['empruntes'] ?? 0,
                $l['perdus'] ?? 0,
            ];
        }

        return [
            'titre' => 'Rapport du Fonds Documentaire',
            'site_nom' => $report['site']['nom'] ?? 'Site',
            'date_generation' => $report['date_generation'] ?? now()->toDateTimeString(),
            'kpis' => $kpis,
            'tables' => [
                [
                    'titre' => 'Répartition par Catégorie',
                    'headers' => ['Catégorie', 'Livres', 'Exemplaires', 'Disponibles', 'Empruntés', 'Perdus'],
                    'rows' => $catRows,
                ],
                [
                    'titre' => 'Détail des Ouvrages',
                    'headers' => ['Code', 'Titre', 'Auteur', 'Catégorie', 'Total ex.', 'Disponibles', 'Empruntés', 'Perdus'],
                    'rows' => $livreRows,
                ],
            ],
        ];
    }

    /**
     * Convertit le rapport Emprunts en structure exportable.
     */
    public function formatEmpruntsData(array $report): array
    {
        $kpis = [
            'Emprunts réalisés' => $report['synthese']['emprunts_realises'] ?? 0,
            'Retours effectués' => $report['synthese']['retours_effectues'] ?? 0,
            'Emprunts en cours' => $report['synthese']['emprunts_en_cours'] ?? 0,
            'Emprunts en retard' => $report['synthese']['emprunts_en_retard'] ?? 0,
            'Consultations sur place' => $report['synthese']['consultations_sur_place'] ?? 0,
            'Taux de retour (%)' => ($report['synthese']['taux_retour_pourcentage'] ?? 0) . ' %',
        ];

        $evolRows = [];
        foreach ($report['evolution_mensuelle'] ?? [] as $m) {
            $evolRows[] = [
                $m['periode'] ?? '',
                $m['mois_nom'] ?? '',
                $m['emprunts'] ?? 0,
                $m['retours'] ?? 0,
                $m['consultations'] ?? 0,
            ];
        }

        return [
            'titre' => 'Rapport des Emprunts, Retours et Consultations',
            'site_nom' => $report['site']['nom'] ?? 'Site',
            'periode' => "Du {$report['periode']['date_debut']} au {$report['periode']['date_fin']}",
            'date_generation' => $report['date_generation'] ?? now()->toDateTimeString(),
            'kpis' => $kpis,
            'tables' => [
                [
                    'titre' => 'Évolution Mensuelle',
                    'headers' => ['Période', 'Mois', 'Emprunts', 'Retours', 'Consultations'],
                    'rows' => $evolRows,
                ],
            ],
        ];
    }

    /**
     * Convertit le rapport Retards en structure exportable.
     */
    public function formatRetardsData(array $report): array
    {
        $kpis = [
            'Total retards actifs' => $report['synthese_tranches']['total_retards_actifs'] ?? 0,
            'Moins de 7 jours' => $report['synthese_tranches']['moins_de_7_jours'] ?? 0,
            'De 7 à 30 jours' => $report['synthese_tranches']['de_7_a_30_jours'] ?? 0,
            'Plus de 30 jours' => $report['synthese_tranches']['plus_de_30_jours'] ?? 0,
        ];

        $rows = [];
        foreach ($report['retards'] ?? [] as $r) {
            $rows[] = [
                $r['livre']['titre'] ?? '',
                $r['exemplaire']['code'] ?? '',
                ($r['usager']['prenom'] ?? '') . ' ' . ($r['usager']['nom'] ?? ''),
                $r['usager']['numero'] ?? '',
                $r['date_emprunt'] ?? '',
                $r['date_retour_prevue'] ?? '',
                $r['jours_retard'] ?? 0,
                $r['tranche_retard'] ?? '',
            ];
        }

        return [
            'titre' => 'Rapport des Retards d\'Emprunts',
            'site_nom' => $report['site']['nom'] ?? 'Site',
            'date_generation' => now()->toDateTimeString(),
            'kpis' => $kpis,
            'tables' => [
                [
                    'titre' => 'Liste des Emprunts en Retard',
                    'headers' => ['Titre', 'Code exemplaire', 'Usager', 'N° Usager', 'Date emprunt', 'Date retour prévue', 'Jours retard', 'Tranche'],
                    'rows' => $rows,
                ],
            ],
        ];
    }

    /**
     * Convertit le rapport Acquisitions en structure exportable.
     */
    public function formatAcquisitionsData(array $report): array
    {
        $kpis = [
            'Total opérations' => $report['synthese']['total_operations'] ?? 0,
            'Quantité totale acquise' => $report['synthese']['quantite_totale_acquise'] ?? 0,
            'Opérations d\'achat' => $report['synthese']['achats']['nombre_operations'] ?? 0,
            'Quantité par achat' => $report['synthese']['achats']['quantite_acquise'] ?? 0,
            'Montant des achats (FCFA)' => number_format($report['synthese']['achats']['montant_total'] ?? 0, 0, ',', ' '),
            'Opérations de don' => $report['synthese']['dons']['nombre_operations'] ?? 0,
            'Quantité par don' => $report['synthese']['dons']['quantite_acquise'] ?? 0,
        ];

        $evolRows = [];
        foreach ($report['evolution_mensuelle'] ?? [] as $m) {
            $evolRows[] = [
                $m['periode'] ?? '',
                $m['mois_nom'] ?? '',
                $m['total_operations'] ?? 0,
                $m['quantite_totale'] ?? 0,
                $m['quantite_achats'] ?? 0,
                number_format($m['montant_achats'] ?? 0, 0, ',', ' '),
                $m['quantite_dons'] ?? 0,
            ];
        }

        return [
            'titre' => 'Rapport des Acquisitions',
            'site_nom' => $report['site']['nom'] ?? 'Site',
            'periode' => "Du {$report['periode']['date_debut']} au {$report['periode']['date_fin']}",
            'date_generation' => now()->toDateTimeString(),
            'kpis' => $kpis,
            'tables' => [
                [
                    'titre' => 'Évolution Mensuelle des Acquisitions',
                    'headers' => ['Période', 'Mois', 'Opérations', 'Total Quantité', 'Qté Achats', 'Montant Achats', 'Qté Dons'],
                    'rows' => $evolRows,
                ],
            ],
        ];
    }

    /**
     * Convertit le rapport Historique d'inventaire en structure exportable.
     */
    public function formatInventaireData(array $report): array
    {
        $rows = [];
        foreach ($report['snapshots'] ?? [] as $s) {
            $rows[] = [
                $s['date_snapshot'] ?? '',
                $s['total_livres'] ?? 0,
                $s['total_exemplaires'] ?? 0,
                $s['exemplaires_disponibles'] ?? 0,
                $s['exemplaires_empruntes'] ?? 0,
                $s['exemplaires_en_consultation'] ?? 0,
                $s['exemplaires_reserves'] ?? 0,
                $s['exemplaires_indisponibles'] ?? 0,
                $s['exemplaires_perdus'] ?? 0,
            ];
        }

        return [
            'titre' => 'Rapport Historique des Inventaires',
            'site_nom' => $report['site']['nom'] ?? 'Site',
            'periode' => "Du {$report['periode']['date_debut']} au {$report['periode']['date_fin']}",
            'date_generation' => now()->toDateTimeString(),
            'kpis' => [
                'Snapshots disponibles' => $report['snapshots_disponibles'] ?? 0,
            ],
            'tables' => [
                [
                    'titre' => 'Historique des Snapshots d\'Inventaire',
                    'headers' => ['Date', 'Livres', 'Exemplaires', 'Disponibles', 'Empruntés', 'Consultation', 'Réservés', 'Indisponibles', 'Perdus'],
                    'rows' => $rows,
                ],
            ],
        ];
    }
}
