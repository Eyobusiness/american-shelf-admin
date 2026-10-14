<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }} - American Shelf</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333333;
            margin: 20px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .logo-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .meta-info {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 5px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: center;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
        }
        .kpi-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #1e3a8a;
        }
        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
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
