<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }} - American Shelf</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 10mm 12mm 14mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        .header {
            border-bottom: 2.5px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-info {
            text-align: right;
            font-size: 9px;
            color: #475569;
            line-height: 1.4;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 3px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 3px;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .kpi-container {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 12px;
        }
        .kpi-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
        }
        .kpi-value {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
        }
        .kpi-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 14px;
            font-size: 9px;
            table-layout: auto;
        }
        table.data-table thead {
            display: table-header-group;
        }
        table.data-table tr {
            page-break-inside: avoid;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #1e3a8a;
            font-size: 9px;
            white-space: nowrap;
        }
        table.data-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 8.5px;
            word-wrap: break-word;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            background-color: #ffffff;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="logo-title">American Shelf - Abobo</div>
                    <div class="report-title">{{ $titre }}</div>
                </td>
                <td class="meta-info">
                    <div><strong>Site :</strong> {{ $site_nom ?? 'Tous les sites' }}</div>
                    @if(!empty($periode))
                        <div><strong>Période :</strong> {{ $periode }}</div>
                    @endif
                    <div><strong>Généré le :</strong> {{ $date_generation }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($kpis))
        <div class="section-title">Indicateurs clés</div>
        <table class="kpi-container" cellspacing="5" cellpadding="0">
            <tr>
                @foreach($kpis as $label => $val)
                    <td class="kpi-box">
                        <div class="kpi-value">{{ is_numeric($val) ? number_format($val, 0, ',', ' ') : $val }}</div>
                        <div class="kpi-label">{{ $label }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if(!empty($tables))
        @foreach($tables as $table)
            <div class="section-title">{{ $table['titre'] ?? 'Données détaillées' }}</div>
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach($table['headers'] as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($table['headers']) }}" class="text-center" style="padding: 15px; color: #94a3b8;">
                                Aucune donnée disponible pour cette section.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="footer">
        American Shelf Abobo — Système Intégré de Gestion de Bibliothèque — Document confidentiel généré automatiquement
    </div>
</body>
</html>
